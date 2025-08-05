<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Facades\Http;

class ResizeImagesController extends Controller
{
    private const imageLimit = 20;
    private const batchSize = 5;
    private $file;
    private $movieId;
    private $originalMovieId;
    private $movieType;
    private $dirPosterName;
    private $posterOriginalId;
    private $posterRussianId;
    private $posterCharactersId = [];
    private $posterAlternativeId = [];
    private $posterWallpaperId;
    private $modelPoster;
    private $modelImages;

    public function index(Request $request)
    {
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', '900');
        ini_set('max_input_time', '900');
        $requestMovieId = get_id_from_url($request->get('id_movie') ?? null, '/tt\d{1,10}/') ?? null;
        if (!empty($requestMovieId)) {
            try {
                $hasher = new IdHasher($requestMovieId);
                $this->originalMovieId = $hasher->isResultHash() ? $hasher->getResult() : $requestMovieId;
                $this->movieId = $hasher->isResultHash() ? $requestMovieId : $hasher->getResult();
                //Log::debug("ID-HASH-PROCESSED ---> ORIGINAL-ID: {$this->originalMovieId}, HASHED-ID: {$this->movieId}");
            } catch (\InvalidArgumentException $e) {
                Log::warning("ID-HASH-ERROR ---> INPUT: {$requestMovieId}, ERROR: {$e->getMessage()}");
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid movie ID format'
                ], 400);
            }
            $assignPosters = AssignPoster::where('id_movie', $this->originalMovieId )->get()->toArray();
            if (!empty($assignPosters)) {
                $this->movieType = $assignPosters[0]['type_film'];
                $this->posterOriginalId = $assignPosters[0]['id_poster_original'] ?? null;
                $this->posterRussianId = $assignPosters[0]['id_poster_ru'] ?? null;
                $this->posterCharactersId = json_decode($assignPosters[0]['id_posters_characters'], true) ?? [];
                $this->posterAlternativeId = json_decode($assignPosters[0]['id_posters_alternative'], true) ?? [];
                $this->posterWallpaperId = $assignPosters[0]['id_wallpaper'] ?? null;
                $this->resizePosters();
                $this->resizeImages();
            }
        }
        return [
            'success' => true,
            'status' => 200,
        ];
    }

    private function resizeImages()
    {
        $this->modelImages = convertVariableToModelName('Images', getTableSegmentOrTypeId($this->movieType), ['App', 'Models']);
        $moviesImages = $this->modelImages::where('id_movie', $this->originalMovieId )->inRandomOrder()->limit(self::imageLimit)->get(['srcset', 'id'])->toArray();

        $this->dirPosterName = 'images';
        //Log::debug("RESIZE-IMAGES-START--ID--{$this->movieId} ---> IMAGES-COUNT: " . count($moviesImages));
        if (empty($moviesImages)) {
            Log::info("NO-IMAGES-FOUND--ID--{$this->movieId} ---> No images to process in 'images' directory");
            return;
        }
        $urls = array_map(function ($image) {
          if (!empty($image['srcset'])){
              return getImageUrlByWidth($image['srcset']);
          }
        }, $moviesImages);
        $ids = array_column($moviesImages, 'id');
        //Log::debug("RESIZE-IMAGES-URLS--ID--{$this->movieId} ---> URLS: " . json_encode($urls));
        //Log::debug("RESIZE-IMAGES-IDS--ID--{$this->movieId} ---> IDS: " . json_encode($ids));

        $validUrls = array_filter($urls, function ($url) {
            return !empty($url) && filter_var($url, FILTER_VALIDATE_URL);
        });
        if (empty($validUrls)) {
            Log::info("NO-VALID-URLS--ID--{$this->movieId} ---> All URLs are invalid or empty");
            return;
        }

        $files = $this->getFilesFromSrcBatch($urls, $ids);
        //Log::debug("RESIZE-IMAGES-FILES--ID--{$this->movieId} ---> FILES-RECEIVED: " . json_encode(array_keys($files)));

        $batchFiles = [];
        foreach ($moviesImages as $index => $image) {
            $this->file = $files[$image['id']] ?? null;
            //Log::debug("PROCESSING-IMAGE--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}, URL: {$urls[$index]}, HAS-FILE: " . (!empty($this->file['content']) ? 'Yes' : 'No'));
            if (!empty($this->file['content'])) {
                if ($this->putFileToTempDirectory($image['id'])) {
                    $batchFiles[] = [
                        'fileName' => $image['id'],
                        'paths' => $this->saveImages($image['id']),
                    ];
                } else {
                    Log::warning("FAILED-TO-SAVE-TEMP--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}");
                }
            } else {
                Log::warning("NO-CONTENT--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}, URL: {$urls[$index]}");
            }
        }

        $this->sendBatchFiles($batchFiles);
    }

    private function resizePosters()
    {
        $this->modelPoster = convertVariableToModelName('Posters', getTableSegmentOrTypeId($this->movieType), ['App', 'Models']);
        $this->compressOriginalPoster();
        $this->compressRussianPoster();
        $this->compressCharactersPoster();
        $this->compressAlternativePoster();
        $this->compressWallpaperPoster();
    }

    private function compressOriginalPoster()
    {
        $originalId = $this->modelPoster::where('id', $this->posterOriginalId)->where('id_movie', $this->originalMovieId)->get('srcset')->toArray();
        $this->dirPosterName = 'original_poster';
        $dirResizeThumbsArr = [['dir' => 'small', 'width' => 200, 'maxHeight' => 800], ['dir' => 'medium', 'width' => 400, 'maxHeight' => 1200]];
        if (!empty($originalId)) {
            $url = getImageUrlByWidth($originalId[0]['srcset']);
            $this->file = $this->getFileFromSrc($url);
            if (!empty($this->file['content'])) {
                //Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$this->posterOriginalId}, URL: {$url}");
                if ($this->putFileToTempDirectory($this->posterOriginalId)) {
                    $batchFiles = [];
                    foreach ($dirResizeThumbsArr as $item) {
                        $batchFiles[] = [
                            'fileName' => $this->posterOriginalId,
                            'paths' => $this->saveThumbnails($item, $this->posterOriginalId),
                        ];
                    }
                    $batchFiles[] = [
                        'fileName' => $this->posterOriginalId,
                        'paths' => $this->saveFullSize($this->posterOriginalId),
                    ];
                    $this->sendBatchFiles($batchFiles);
                }
            }
        }
    }

    private function compressRussianPoster()
    {
        $russianId = $this->modelPoster::where('id', $this->posterRussianId)->where('id_movie', $this->originalMovieId)->get('srcset')->toArray();
        $this->dirPosterName = 'russian_poster';
        $dirResizeThumbsArr = [['dir' => 'small', 'width' => 200, 'maxHeight' => 800], ['dir' => 'medium', 'width' => 400, 'maxHeight' => 1200]];
        if (!empty($russianId)) {
            $url = getImageUrlByWidth($russianId[0]['srcset']);
            $this->file = $this->getFileFromSrc($url);
            if (!empty($this->file['content'])) {
                //Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$this->posterRussianId}, URL: {$url}");
                if ($this->putFileToTempDirectory($this->posterRussianId)) {
                    $batchFiles = [];
                    foreach ($dirResizeThumbsArr as $item) {
                        $batchFiles[] = [
                            'fileName' => $this->posterRussianId,
                            'paths' => $this->saveThumbnails($item, $this->posterRussianId),
                        ];
                    }
                    $batchFiles[] = [
                        'fileName' => $this->posterRussianId,
                        'paths' => $this->saveFullSize($this->posterRussianId),
                    ];
                    $this->sendBatchFiles($batchFiles);
                }
            }
        }
    }

    private function compressCharactersPoster()
    {
        if ($posterIdArr = $this->posterCharactersId) {
            $this->dirPosterName = 'characters_posters';
            $dirResizeThumbsArr = ['dir' => 'small', 'width' => 300, 'maxHeight' => 800];
            $charactersIdArr = $this->modelPoster::where('id_movie', $this->originalMovieId)->whereIn('id', $posterIdArr)->get(['srcset', 'id'])->toArray();
            if (!empty($charactersIdArr)) {
                $urls = array_map(function ($item) {
                    return getImageUrlByWidth($item['srcset']);
                }, $charactersIdArr);
                $ids = array_column($charactersIdArr, 'id');
                $files = $this->getFilesFromSrcBatch($urls, $ids);
                $batchFiles = [];
                foreach ($charactersIdArr as $index => $item) {
                    $fileName = $item['id'];
                    $this->file = $files[$fileName] ?? null;
                    if (!empty($this->file['content'])) {
                        //Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$fileName}, URL: {$urls[$index]}");
                        if ($this->putFileToTempDirectory($fileName)) {
                            $batchFiles[] = [
                                'fileName' => $fileName,
                                'paths' => $this->saveThumbnails($dirResizeThumbsArr, $fileName),
                            ];
                            $batchFiles[] = [
                                'fileName' => $fileName,
                                'paths' => $this->saveFullSize($fileName),
                            ];
                        }
                    }
                }
                $this->sendBatchFiles($batchFiles);
            }
        }
    }

    private function compressAlternativePoster()
    {
        if ($posterIdArr = $this->posterAlternativeId) {
            $this->dirPosterName = 'alt_posters';
            $dirResizeThumbsArr = ['dir' => 'small', 'width' => 300, 'maxHeight' => 800];
            $altIdArr = $this->modelPoster::where('id_movie', $this->originalMovieId)->whereIn('id', $posterIdArr)->get(['srcset', 'id'])->toArray();
            if (!empty($altIdArr)) {
                $urls = array_map(function ($item) {
                    return getImageUrlByWidth($item['srcset']);
                }, $altIdArr);
                $ids = array_column($altIdArr, 'id');
                $files = $this->getFilesFromSrcBatch($urls, $ids);
                $batchFiles = [];
                foreach ($altIdArr as $index => $item) {
                    $fileName = $item['id'];
                    $this->file = $files[$fileName] ?? null;
                    if (!empty($this->file['content'])) {
                        //Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$fileName}, URL: {$urls[$index]}");
                        if ($this->putFileToTempDirectory($fileName)) {
                            $batchFiles[] = [
                                'fileName' => $fileName,
                                'paths' => $this->saveThumbnails($dirResizeThumbsArr, $fileName),
                            ];
                            $batchFiles[] = [
                                'fileName' => $fileName,
                                'paths' => $this->saveFullSize($fileName),
                            ];
                        }
                    }
                }
                $this->sendBatchFiles($batchFiles);
            }
        }
    }

    private function compressWallpaperPoster()
    {
        $wallpaperId = $this->modelPoster::where('id', $this->posterWallpaperId)->where('id_movie', $this->originalMovieId)->get('srcset')->toArray();
        $this->dirPosterName = 'wallpaper';
        if (!empty($wallpaperId)) {
            $url = getImageUrlByWidth($wallpaperId[0]['srcset']);
            $this->file = $this->getFileFromSrc($url);
            if (!empty($this->file['content'])) {
                //Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$this->posterWallpaperId}, URL: {$url}");
                if ($this->putFileToTempDirectory($this->posterWallpaperId)) {
                    $batchFiles = [
                        [
                            'fileName' => $this->posterWallpaperId,
                            'paths' => $this->saveFullSize($this->posterWallpaperId),
                        ],
                    ];
                    $this->sendBatchFiles($batchFiles);
                }
            }
        }
    }

    private function saveFullSize($fileName)
    {
        $relativePath = "/{$this->movieId}/{$this->dirPosterName}/{$fileName}.jpg";
        $tempPath = Storage::disk('temp')->path($relativePath);
        $resizedPath = "/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/full_size/{$fileName}.jpg";
        if (!$this->putFileToTempDirectory($fileName)) {
            Log::info("SKIP-SAVE-FULLSIZE--ID--{$this->movieId} ---> FILE: {$fileName}");
            return [];
        }

        try {
            $fullSize = Image::read($tempPath);
            $originalWidth = $fullSize->width();
            $originalHeight = $fullSize->height();
            $fileSize = $fullSize->exif('FILE.FileSize') ?? filesize($tempPath);
            $quality = 90;
            //Log::debug("ORIGINAL-DIMENSIONS--ID--{$this->movieId} ---> FILE: {$fileName}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");

            if ($originalWidth > 2000 || $originalHeight > 3000) {
                $ratio = $originalWidth / $originalHeight;
                if ($originalWidth > 2000) {
                    $fullSize->resize(2000, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    if ($fullSize->height() > 3000) {
                        $fullSize->resize(null, 3000, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                } elseif ($originalHeight > 3000) {
                    $fullSize->resize(null, 3000, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }
                if ($originalWidth > 3000 || $originalHeight > 4500) {
                    $quality = 80;
                }
            }

            $savePath = $resizedPath;
            Storage::disk('resized')->put($savePath, $fullSize->encode(new JpegEncoder(quality: $quality)));
            $savedSize = Storage::disk('resized')->size($savePath) / 1024;
            //Log::debug("FULLSIZE-SAVED-TEMP--ID--{$this->movieId} ---> FILE: {$fileName}, ORIGINAL-SIZE: {$fileSize}KB, SAVED-SIZE: {$savedSize}KB, QUALITY: {$quality}, RESIZED-WIDTH: {$fullSize->width()}, RESIZED-HEIGHT: {$fullSize->height()}");

            unset($fullSize);
            return ['full_size' => $savePath];
        } catch (\Exception $e) {
            Log::info("ERROR-SAVE-FULLSIZE--ID--{$this->movieId} ---> FILE: {$fileName}, ERROR: {$e->getMessage()}");
            return [];
        }
    }

    private function saveThumbnails($params, $fileName)
    {
        $relativePath = "/{$this->movieId}/{$this->dirPosterName}/{$fileName}.jpg";
        $tempPath = Storage::disk('temp')->path($relativePath);
        $resizedPath = "/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/{$params['dir']}/{$fileName}.jpg";
        if (!$this->putFileToTempDirectory($fileName)) {
            Log::info("SKIP-SAVE-THUMBNAILS--ID--{$this->movieId} ---> FILE: {$fileName}, DIR: {$params['dir']}");
            return [];
        }

        try {
            $image = Image::read($tempPath);
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            //Log::debug("ORIGINAL-DIMENSIONS--ID--{$this->movieId} ---> FILE: {$fileName}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");

            $maxWidth = $params['width'];
            $maxHeight = $params['maxHeight'];
            $ratio = $originalWidth / $originalHeight;

            if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
                $targetWidth = $maxWidth;
                $targetHeight = (int)($maxWidth / $ratio);

                if ($targetHeight > $maxHeight) {
                    $targetHeight = $maxHeight;
                    $targetWidth = (int)($maxHeight * $ratio);
                }

                $image->resize($targetWidth, $targetHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $savePath = $resizedPath;
            Storage::disk('resized')->put($savePath, $image->encode(new JpegEncoder(quality: 70)));
            $savedSize = Storage::disk('resized')->size($savePath) / 1024;
            //Log::debug("THUMBNAIL-SAVED-TEMP--ID--{$this->movieId} ---> FILE: {$fileName}, DIR: {$params['dir']}, SIZE: {$savedSize}KB, RESIZED-WIDTH: {$image->width()}, RESIZED-HEIGHT: {$image->height()}");

            unset($image);
            return [$params['dir'] => $savePath];
        } catch (\Exception $e) {
            Log::info("ERROR-SAVE-THUMBNAILS--ID--{$this->movieId} ---> FILE: {$fileName}, DIR: {$params['dir']}, ERROR: {$e->getMessage()}");
            return [];
        }
    }

    private function saveImages($fileName)
    {
        $relativePath = "/{$this->movieId}/{$this->dirPosterName}/{$fileName}.jpg";
        $tempPath = Storage::disk('temp')->path($relativePath);
        if (!$this->putFileToTempDirectory($fileName)) {
            Log::info("SKIP-SAVE-IMAGES--ID--{$this->movieId} ---> FILE: {$fileName}");
            return [];
        }

        $paths = [];
        try {
            $small = Image::read($tempPath);
            $originalWidth = $small->width();
            $originalHeight = $small->height();
            $fileSize = $small->exif('FILE.FileSize') ?? filesize($tempPath);
            $quality = 70;
            //Log::debug("ORIGINAL-DIMENSIONS--ID--{$this->movieId} ---> FILE: {$fileName}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");

            $small->cover(300, 200, 'center');
            $savePathSmall = "/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/small/{$fileName}.jpg";
            Storage::disk('resized')->put($savePathSmall, $small->encode(new JpegEncoder(quality: $quality)));
            $savedSizeSmall = Storage::disk('resized')->size($savePathSmall) / 1024;
            //Log::debug("SMALL-SAVED-TEMP--ID--{$this->movieId} ---> FILE: {$fileName}, ORIGINAL-SIZE: {$fileSize}KB, SAVED-SIZE: {$savedSizeSmall}KB");
            $paths['small'] = $savePathSmall;

            $fullSize = Image::read($tempPath);
            if ($originalWidth > 3000 || $originalHeight > 4000) {
                $ratio = $originalWidth / $originalHeight;
                if ($originalWidth > 3000) {
                    $fullSize->resize(3000, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    if ($fullSize->height() > 4000) {
                        $fullSize->resize(null, 4000, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                } elseif ($originalHeight > 4000) {
                    $fullSize->resize(null, 4000, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }
                if ($originalWidth > 5000 || $originalHeight > 6000) {
                    $quality = 80;
                }
            }
            $savePathFull = "/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/full_size/{$fileName}.jpg";
            Storage::disk('resized')->put($savePathFull, $fullSize->encode(new JpegEncoder(quality: $quality)));
            $savedSizeFull = Storage::disk('resized')->size($savePathFull) / 1024;
            //Log::debug("FULLSIZE-SAVED-TEMP--ID--{$this->movieId} ---> FILE: {$fileName}, ORIGINAL-SIZE: {$fileSize}KB, SAVED-SIZE: {$savedSizeFull}KB, QUALITY: {$quality}");
            $paths['full_size'] = $savePathFull;

            unset($fullSize);
            unset($small);
            return $paths;
        } catch (\Exception $e) {
            Log::info("ERROR-SAVE-IMAGES--ID--{$this->movieId} ---> FILE: {$fileName}, ERROR: {$e->getMessage()}");
            return [];
        }
    }

    private function sendBatchFiles($batchFiles)
    {
        if (empty($batchFiles)) {
            Log::info("NO-BATCH-FILES--ID--{$this->movieId} ---> Nothing to send");
            return;
        }

        $maxRetries = 3;
        $batchSize = self::batchSize;
        $chunks = array_chunk($batchFiles, $batchSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            $retryCount = 0;
            $success = false;

            while ($retryCount < $maxRetries && !$success) {
                try {
                    $http = Http::withToken(env('API_TOKEN'));
                    $multipart = [];
                    $fileIndex = 0;
                    foreach ($chunk as $batch) {
                        foreach ($batch['paths'] as $size => $path) {
                            $filePath = Storage::disk('resized')->path($path);
                            if (Storage::disk('resized')->exists($path)) {
                                //Log::debug("BATCH-PREPARED-FILE--ID--{$this->movieId} ---> FILE: {$batch['fileName']}, SIZE: {$size}, DIR: {$this->dirPosterName}, PATH: {$path}, FILE-EXISTS: true");
                                $multipart[] = [
                                    'name' => "images[{$fileIndex}]",
                                    'contents' => file_get_contents($filePath),
                                    'filename' => "{$batch['fileName']}.jpg",
                                ];
                                $multipart[] = [
                                    'name' => "images[{$fileIndex}][size]",
                                    'contents' => $size,
                                ];
                                $multipart[] = [
                                    'name' => "images[{$fileIndex}][dirPosterName]",
                                    'contents' => $this->dirPosterName,
                                ];
                                //Log::debug("BATCH-ADDED-TO-MULTIPART--ID--{$this->movieId} ---> FILE: {$batch['fileName']}, SIZE: {$size}, DIR: {$this->dirPosterName}, INDEX: {$fileIndex}");
                                $fileIndex++;
                            } else {
                                Log::error("FILE-MISSING--ID--{$this->movieId} ---> PATH: {$path}");
                            }
                        }
                    }
                    //Log::debug("BATCH-MULTIPART-STRUCTURE--ID--{$this->movieId} ---> CHUNK: {$chunkIndex}, MULTIPART-COUNT: " . count($multipart));
                    $url = env('API_HOST_URL') . "/api/images/batch/{$this->movieType}/{$this->movieId}";
                    Log::info("BATCH-SEND--ID--{$this->movieId} ---> URL: {$url}, CHUNK: {$chunkIndex}, FILES: {$fileIndex}");
                    $response = $http->asMultipart()->post($url, $multipart);

                    if ($response->successful()) {
                        Log::info("BATCH-UPLOADED--ID--{$this->movieId} ---> CHUNK: {$chunkIndex}, RESPONSE: " . $response->body());
                        $success = true;
                        foreach ($chunk as $batch) {
                            foreach ($batch['paths'] as $path) {
                                Storage::disk('resized')->delete($path);
                                //Log::debug("DELETED-RESIZED--ID--{$this->movieId} ---> PATH: {$path}");
                            }
                        }
                    } elseif ($response->status() === 429) {
                        $retryAfter = $response->header('Retry-After') ?? 10;
                        Log::warning("TOO-MANY-REQUESTS--ID--{$this->movieId} ---> CHUNK: {$chunkIndex}, RETRY-AFTER: {$retryAfter}s");
                        sleep($retryAfter);
                        $retryCount++;
                    } else {
                        Log::error("BATCH-FAILED--ID--{$this->movieId} ---> CHUNK: {$chunkIndex}, STATUS: {$response->status()}, ERROR: " . json_encode($response->body(), JSON_INVALID_UTF8_SUBSTITUTE));
                        $retryCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("BATCH-ERROR--ID--{$this->movieId} ---> CHUNK: {$chunkIndex}, ERROR: " . $e->getMessage());
                    $retryCount++;
                    sleep(10);
                }
            }

            if (!$success) {
                Log::error("BATCH-FAILED-AFTER-RETRIES--ID--{$this->movieId} ---> CHUNK: {$chunkIndex}");
            }
        }

        Storage::disk('temp')->deleteDirectory("/{$this->movieId}");
        Storage::disk('resized')->deleteDirectory("/{$this->movieType}/{$this->movieId}");
        Log::info("DELETED-TEMP--ID--{$this->movieId} ---> PATH: /temp/{$this->movieId}, /resized/{$this->movieType}/{$this->movieId}");
    }

    private function putFileToTempDirectory($imageId)
    {
        if (empty($this->file['content'])) {
            Log::info("EMPTY-FILE--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}");
            return false;
        }

        $relativePath = "/{$this->movieId}/{$this->dirPosterName}/{$imageId}.jpg";
        $tempPath = Storage::disk('temp')->path($relativePath);
        $directory = dirname($relativePath);

        try {
            if (!Storage::disk('temp')->exists($directory)) {
                Storage::disk('temp')->makeDirectory($directory, 0755, true, true);
                Log::info("CREATED-DIRECTORY--ID--{$this->movieId} ---> PATH: {$directory}");
            }
        } catch (\Exception $e) {
            Log::error("FAILED-CREATE-DIRECTORY--ID--{$this->movieId} ---> PATH: {$directory}, ERROR: {$e->getMessage()}");
            return false;
        }

        $absoluteDirPath = Storage::disk('temp')->path($directory);
        if (!is_writable($absoluteDirPath)) {
            Log::error("DIR-NOT-WRITABLE--ID--{$this->movieId} ---> PATH: {$absoluteDirPath}");
            return false;
        }

        $maxRetries = 3;
        $retryCount = 0;
        $success = false;

        while ($retryCount < $maxRetries && !$success) {
            try {
                $result = Storage::disk('temp')->put($relativePath, $this->file['content']);
                if (!$result) {
                    Log::error("FAILED-WRITE-FILE--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}, PATH: {$tempPath}");
                    return false;
                }
            } catch (\Exception $e) {
                Log::error("ERROR-WRITE-FILE--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}, PATH: {$tempPath}, ERROR: {$e->getMessage()}");
                return false;
            }

            $maxAttempts = 20;
            $attempt = 0;
            $actualSize = filesize($tempPath);
            $expectedSize = $this->file['expectedSize'];

            while ($attempt < $maxAttempts) {
                if ($expectedSize !== null && $actualSize >= $expectedSize) {
                    $success = true;
                    break;
                } elseif ($expectedSize === null) {
                    if (is_readable($tempPath) && $actualSize > 0) {
                        $success = true;
                        break;
                    }
                }

                sleep(1);
                clearstatcache(true, $tempPath);
                $actualSize = filesize($tempPath);
                $attempt++;
            }

            if (!$success) {
                Log::info("INCOMPLETE-FILE-RETRY--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}, SIZE: {$actualSize}, EXPECTED: {$expectedSize}, RETRY: {$retryCount}");
                $retryCount++;
                if ($retryCount < $maxRetries) {
                    $this->file = $this->getFileFromSrc($this->file['lastUrl'] ?? '');
                }
            }
        }

        if (!$success || $actualSize === 0 || !is_readable($tempPath)) {
            Log::info("INCOMPLETE-FILE--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}, SIZE: {$actualSize}, EXPECTED: {$expectedSize}");
            return false;
        }

        try {
            $image = Image::read($tempPath);
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            Log::debug("TEMP-FILE-DIMENSIONS--ID--{$this->originalMovieId} ---> HASHED-ID: {$this->movieId}, IMAGE-ID: {$imageId}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");
            unset($image);
        } catch (\Exception $e) {
            Log::info("INVALID-IMAGE--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}, ERROR: {$e->getMessage()}");
            return false;
        }

        return true;
    }

    private function getFileFromSrc($url)
    {
        $content = null;
        $expectedSize = null;

        Log::debug("GET-FILE-FROM-SRC--ID--{$this->movieId} ---> URL: {$url}");
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Log::warning("INVALID-URL--ID--{$this->movieId} ---> URL: {$url}");
            return ['content' => null, 'expectedSize' => null, 'lastUrl' => $url];
        }

        try {
            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 10,
                'allow_redirects' => true,
            ]);

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; YourApp/1.0)',
                ],
                'http_errors' => false,
            ]);

            Log::debug("GET-FILE-RESPONSE--ID--{$this->movieId} ---> URL: {$url}, STATUS: {$response->getStatusCode()}");
            if ($response->getStatusCode() === 200) {
                $content = $response->getBody()->getContents();
                $expectedSize = $response->hasHeader('Content-Length') ? (int) $response->getHeaderLine('Content-Length') : null;
                $finfo = finfo_open();
                $mimeType = finfo_buffer($finfo, $content, FILEINFO_MIME_TYPE);
                finfo_close($finfo);
                if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
                    Log::warning("INVALID-MIME-TYPE--ID--{$this->movieId} ---> URL: {$url}, MIME: {$mimeType}");
                    return ['content' => null, 'expectedSize' => null, 'lastUrl' => $url];
                }
            } else {
                Log::info("NOT-FOUND--ID--{$this->movieId} ---> SRC: {$url}, STATUS: {$response->getStatusCode()}");
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::info("EXCEPTION--ID--{$this->movieId} ---> SRC: {$url}, ERROR: {$e->getMessage()}");
        } catch (\Exception $e) {
            Log::info("GENERAL-EXCEPTION--ID--{$this->movieId} ---> SRC: {$url}, ERROR: {$e->getMessage()}");
        }

        return ['content' => $content, 'expectedSize' => $expectedSize, 'lastUrl' => $url];
    }

    private function getFilesFromSrcBatch($urls, $ids)
    {
        //Log::debug("GET-FILES-BATCH-START--ID--{$this->movieId} ---> URL-COUNT: " . count($urls) . ", ID-COUNT: " . count($ids));
        if (count($urls) !== count($ids)) {
            Log::error("MISMATCH-URLS-IDS--ID--{$this->movieId} ---> URL-COUNT: " . count($urls) . ", ID-COUNT: " . count($ids));
            return [];
        }

        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'allow_redirects' => true,
        ]);

        $requests = function () use ($urls) {
            foreach ($urls as $index => $url) {
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    //Log::debug("BATCH-REQUEST--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$url}");
                    yield new GuzzleRequest('GET', $url, [
                        'User-Agent' => 'Mozilla/5.0 (compatible; YourApp/1.0)',
                    ]);
                } else {
                    Log::warning("BATCH-SKIP-INVALID-URL--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$url}");
                }
            }
        };

        $results = [];
        $pool = new Pool($client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function ($response, $index) use (&$results, $urls, $ids) {
                //Log::debug("BATCH-FULFILLED--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$urls[$index]}, STATUS: {$response->getStatusCode()}");
                if ($response->getStatusCode() === 200) {
                    $content = $response->getBody()->getContents();
                    $expectedSize = $response->hasHeader('Content-Length') ? (int) $response->getHeaderLine('Content-Length') : null;
                    $results[$ids[$index]] = [
                        'content' => $content,
                        'expectedSize' => $expectedSize,
                        'lastUrl' => $urls[$index],
                    ];
                    //Log::debug("BATCH-SUCCESS--ID--{$this->movieId} ---> INDEX: {$index}, ID: {$ids[$index]}, CONTENT-LENGTH: " . strlen($content));
                } else {
                    Log::info("BATCH-NOT-FOUND--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$urls[$index]}, STATUS: {$response->getStatusCode()}");
                    $results[$ids[$index]] = ['content' => null, 'expectedSize' => null, 'lastUrl' => $urls[$index]];
                }
            },
            'rejected' => function ($reason, $index) use (&$results, $urls, $ids) {
                Log::info("BATCH-EXCEPTION--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$urls[$index]}, ERROR: {$reason->getMessage()}");
                $results[$ids[$index]] = ['content' => null, 'expectedSize' => null, 'lastUrl' => $urls[$index]];
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        //Log::debug("GET-FILES-BATCH-COMPLETE--ID--{$this->movieId} ---> RESULTS: " . json_encode(array_keys($results)));
        return $results;
    }
}
