<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Promise;

class ResizeImagesController
{
    private const imageLimit = 20;
    private $file;
    private $movieId;
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
        $this->movieId = get_id_from_url($request->get('id_movie') ?? null, '/tt\d{1,10}/') ?? null;
        if (!empty($this->movieId)) {
            $assignPosters = AssignPoster::where('id_movie', $this->movieId)->get()->toArray();
            if (!empty($assignPosters)) {
                $this->movieType = $assignPosters[0]['type_film'];
                $this->posterOriginalId = $assignPosters[0]['id_poster_original'] ?? null;
                $this->posterRussianId = $assignPosters[0]['id_poster_ru'] ?? null;
                $this->posterCharactersId = json_decode($assignPosters[0]['id_posters_characters'], true) ?? [];
                $this->posterAlternativeId = json_decode($assignPosters[0]['id_posters_alternative'], true) ?? [];
                $this->posterWallpaperId = $assignPosters[0]['id_wallpaper'] ?? null;
                $this->resizePosters();
                $this->resizeImages();
                $this->deleteTempDirectory();
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
        $moviesImages = $this->modelImages::where('id_movie', $this->movieId)->inRandomOrder()->limit(self::imageLimit)->get(['srcset', 'id'])->toArray();
        $this->dirPosterName = 'images';
        $this->makeResizeDirectory(['small', 'full_size']);
        Log::debug("RESIZE-IMAGES-START--ID--{$this->movieId} ---> IMAGES-COUNT: " . count($moviesImages));
        if (empty($moviesImages)) {
            Log::info("NO-IMAGES-FOUND--ID--{$this->movieId} ---> No images to process in 'images' directory");
            return;
        }

        $urls = array_map(function ($image) {
            return getImageUrlByWidth($image['srcset']);
        }, $moviesImages);
        $ids = array_column($moviesImages, 'id');
        Log::debug("RESIZE-IMAGES-URLS--ID--{$this->movieId} ---> URLS: " . json_encode($urls));
        Log::debug("RESIZE-IMAGES-IDS--ID--{$this->movieId} ---> IDS: " . json_encode($ids));

        $validUrls = array_filter($urls, function ($url) {
            return !empty($url) && filter_var($url, FILTER_VALIDATE_URL);
        });
        if (empty($validUrls)) {
            Log::info("NO-VALID-URLS--ID--{$this->movieId} ---> All URLs are invalid or empty");
            return;
        }

        $files = $this->getFilesFromSrcBatch($urls, $ids);
        Log::debug("RESIZE-IMAGES-FILES--ID--{$this->movieId} ---> FILES-RECEIVED: " . json_encode(array_keys($files)));

        foreach ($moviesImages as $index => $image) {
            $this->file = $files[$image['id']] ?? null;
            Log::debug("PROCESSING-IMAGE--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}, URL: {$urls[$index]}, HAS-FILE: " . (!empty($this->file['content']) ? 'Yes' : 'No'));
            if (!empty($this->file['content'])) {
                if ($this->putFileToTempDirectory($image['id'])) {
                    $this->saveImages($image['id']);
                    Log::debug("IMAGE-SAVED--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}");
                } else {
                    Log::warning("FAILED-TO-SAVE-TEMP--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}");
                }
            } else {
                Log::warning("NO-CONTENT--ID--{$this->movieId} ---> IMAGE-ID: {$image['id']}, URL: {$urls[$index]}");
            }
        }
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
        $originalId = $this->modelPoster::where('id', $this->posterOriginalId)->where('id_movie', $this->movieId)->get('srcset')->toArray();
        $this->dirPosterName = 'original_poster';
        $dirResizeThumbsArr = [['dir' => 'thumb', 'width' => 100, 'maxHeight' => 600], ['dir' => 'small', 'width' => 200, 'maxHeight' => 800], ['dir' => 'medium', 'width' => 400, 'maxHeight' => 1200]];
        if (!empty($originalId)) {
            $this->makeResizeDirectory(['thumb', 'small', 'medium', 'full_size']);
            $url = getImageUrlByWidth($originalId[0]['srcset']);
            $this->file = $this->getFileFromSrc($url);
            if (!empty($this->file['content'])) {
                Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$this->posterOriginalId}, URL: {$url}");
                if ($this->putFileToTempDirectory($this->posterOriginalId)) {
                    foreach ($dirResizeThumbsArr as $item) {
                        $this->saveThumbnails($item, $this->posterOriginalId);
                    }
                    $this->saveFullSize($this->posterOriginalId);
                }
            }
        }
    }

    private function compressRussianPoster()
    {
        $russianId = $this->modelPoster::where('id', $this->posterRussianId)->where('id_movie', $this->movieId)->get('srcset')->toArray();
        $this->dirPosterName = 'russian_poster';
        $dirResizeThumbsArr = [['dir' => 'thumb', 'width' => 100, 'maxHeight' => 600], ['dir' => 'small', 'width' => 200, 'maxHeight' => 800], ['dir' => 'medium', 'width' => 400, 'maxHeight' => 1200]];
        if (!empty($russianId)) {
            $this->makeResizeDirectory(['thumb', 'small', 'medium', 'full_size']);
            $url = getImageUrlByWidth($russianId[0]['srcset']);
            $this->file = $this->getFileFromSrc($url);
            if (!empty($this->file['content'])) {
                Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$this->posterRussianId}, URL: {$url}");
                if ($this->putFileToTempDirectory($this->posterRussianId)) {
                    foreach ($dirResizeThumbsArr as $item) {
                        $this->saveThumbnails($item, $this->posterRussianId);
                    }
                    $this->saveFullSize($this->posterRussianId);
                }
            }
        }
    }

    private function compressCharactersPoster()
    {
        if ($posterIdArr = $this->posterCharactersId) {
            $this->dirPosterName = 'characters_posters';
            $dirResizeThumbsArr = ['dir' => 'small', 'width' => 300, 'maxHeight' => 800];
            $charactersIdArr = $this->modelPoster::where('id_movie', $this->movieId)->whereIn('id', $posterIdArr)->get('srcset')->toArray();
            if (!empty($charactersIdArr)) {
                $this->makeResizeDirectory(['small', 'full_size']);
                $urls = array_map(function ($item) {
                    return getImageUrlByWidth($item['srcset']);
                }, $charactersIdArr);
                $files = $this->getFilesFromSrcBatch($urls, array_keys($charactersIdArr));
                foreach ($charactersIdArr as $k => $id) {
                    $fileName = $k + 1;
                    $this->file = $files[$fileName] ?? null;
                    if (!empty($this->file['content'])) {
                        Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$fileName}, URL: {$urls[$k]}");
                        if ($this->putFileToTempDirectory($fileName)) {
                            $this->saveThumbnails($dirResizeThumbsArr, $fileName);
                            $this->saveFullSize($fileName);
                        }
                    }
                }
            }
        }
    }

    private function compressAlternativePoster()
    {
        if ($posterIdArr = $this->posterAlternativeId) {
            $this->dirPosterName = 'alt_posters';
            $dirResizeThumbsArr = ['dir' => 'small', 'width' => 300, 'maxHeight' => 800];
            $altIdArr = $this->modelPoster::where('id_movie', $this->movieId)->whereIn('id', $posterIdArr)->get('srcset')->toArray();
            if (!empty($altIdArr)) {
                $this->makeResizeDirectory(['small', 'full_size']);
                $urls = array_map(function ($item) {
                    return getImageUrlByWidth($item['srcset']);
                }, $altIdArr);
                $files = $this->getFilesFromSrcBatch($urls, array_keys($altIdArr));
                foreach ($altIdArr as $k => $id) {
                    $fileName = $k + 1;
                    $this->file = $files[$fileName] ?? null;
                    if (!empty($this->file['content'])) {
                        Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$fileName}, URL: {$urls[$k]}");
                        if ($this->putFileToTempDirectory($fileName)) {
                            $this->saveThumbnails($dirResizeThumbsArr, $fileName);
                            $this->saveFullSize($fileName);
                        }
                    }
                }
            }
        }
    }

    private function compressWallpaperPoster()
    {
        $wallpaperId = $this->modelPoster::where('id', $this->posterWallpaperId)->where('id_movie', $this->movieId)->get('srcset')->toArray();
        $this->dirPosterName = 'wallpaper';
        if (!empty($wallpaperId)) {
            $url = getImageUrlByWidth($wallpaperId[0]['srcset']);
            $this->file = $this->getFileFromSrc($url);
            if (!empty($this->file['content'])) {
                $this->makeResizeDirectory(['full_size']);
                Log::debug("PRE-PUT-FILE--ID--{$this->movieId} ---> DIR: {$this->dirPosterName}, IMAGE-ID: {$this->posterWallpaperId}, URL: {$url}");
                if ($this->putFileToTempDirectory($this->posterWallpaperId)) {
                    $this->saveFullSize($this->posterWallpaperId);
                }
            }
        }
    }

    private function saveFullSize($fileName)
    {
        $relativePath = "/temp/{$this->movieId}/{$this->dirPosterName}/{$fileName}.jpg";
        $tempPath = Storage::disk('images')->path($relativePath);
        if (!$this->putFileToTempDirectory($fileName)) {
            Log::info("SKIP-SAVE-FULLSIZE--ID--{$this->movieId} ---> FILE: {$fileName}");
            return;
        }

        try {
            $fullSize = Image::read($tempPath);
            $originalWidth = $fullSize->width();
            $originalHeight = $fullSize->height();
            $fileSize = $fullSize->exif('FILE.FileSize') ?? filesize($tempPath);
            $quality = 90;
            Log::debug("ORIGINAL-DIMENSIONS--ID--{$this->movieId} ---> FILE: {$fileName}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");

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

            $savePath = Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/full_size/{$fileName}.jpg");
            $fullSize->save($savePath, quality: $quality);
            $savedSize = filesize($savePath) / 1024;
            Log::debug("FULLSIZE-SAVED--ID--{$this->movieId} ---> FILE: {$fileName}, ORIGINAL-SIZE: {$fileSize}KB, SAVED-SIZE: {$savedSize}KB, QUALITY: {$quality}, RESIZED-WIDTH: {$fullSize->width()}, RESIZED-HEIGHT: {$fullSize->height()}");
            unset($fullSize);
        } catch (\Exception $e) {
            Log::info("ERROR-SAVE-FULLSIZE--ID--{$this->movieId} ---> FILE: {$fileName}, ERROR: {$e->getMessage()}");
        }
    }

    private function saveThumbnails($params, $fileName)
    {
        $relativePath = "/temp/{$this->movieId}/{$this->dirPosterName}/{$fileName}.jpg";
        $tempPath = Storage::disk('images')->path($relativePath);
        if (!$this->putFileToTempDirectory($fileName)) {
            Log::info("SKIP-SAVE-THUMBNAILS--ID--{$this->movieId} ---> FILE: {$fileName}, DIR: {$params['dir']}");
            return;
        }

        try {
            $image = Image::read($tempPath);
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            Log::debug("ORIGINAL-DIMENSIONS--ID--{$this->movieId} ---> FILE: {$fileName}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");

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

            $savePath = Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/{$params['dir']}/{$fileName}.jpg");
            $image->save($savePath, quality: 70);
            $savedSize = filesize($savePath) / 1024;
            Log::debug("THUMBNAIL-SAVED--ID--{$this->movieId} ---> FILE: {$fileName}, DIR: {$params['dir']}, SIZE: {$savedSize}KB, RESIZED-WIDTH: {$image->width()}, RESIZED-HEIGHT: {$image->height()}");
            unset($image);
        } catch (\Exception $e) {
            Log::info("ERROR-SAVE-THUMBNAILS--ID--{$this->movieId} ---> FILE: {$fileName}, DIR: {$params['dir']}, ERROR: {$e->getMessage()}");
        }
    }

    private function saveImages($fileName)
    {
        $relativePath = "/temp/{$this->movieId}/{$this->dirPosterName}/{$fileName}.jpg";
        $tempPath = Storage::disk('images')->path($relativePath);
        if (!$this->putFileToTempDirectory($fileName)) {
            Log::info("SKIP-SAVE-IMAGES--ID--{$this->movieId} ---> FILE: {$fileName}");
            return;
        }

        try {
            $small = Image::read($tempPath);
            $originalWidth = $small->width();
            $originalHeight = $small->height();
            $fileSize = $small->exif('FILE.FileSize') ?? filesize($tempPath);
            $quality = 70;
            Log::debug("ORIGINAL-DIMENSIONS--ID--{$this->movieId} ---> FILE: {$fileName}, WIDTH: {$originalWidth}, HEIGHT: {$originalHeight}");

            $small->cover(300,200,'center');
            $savePathSmall = Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/small/{$fileName}.jpg");
            $small->save($savePathSmall, quality: $quality);
            $savedSizeSmall = filesize($savePathSmall) / 1024;
            Log::debug("SMALL-SAVED--ID--{$this->movieId} ---> FILE: {$fileName}, ORIGINAL-SIZE: {$fileSize}KB, SAVED-SIZE: {$savedSizeSmall}KB");

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
            $savePathFull = Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/images/full_size/{$fileName}.jpg");
            $fullSize->save($savePathFull, quality: $quality);
            $savedSizeFull = filesize($savePathFull) / 1024;
            Log::debug("FULLSIZE-SAVED--ID--{$this->movieId} ---> FILE: {$fileName}, ORIGINAL-SIZE: {$fileSize}KB, SAVED-SIZE: {$savedSizeFull}KB, QUALITY: {$quality}");
            unset($fullSize);

            unset($small);
        } catch (\Exception $e) {
            Log::info("ERROR-SAVE-IMAGES--ID--{$this->movieId} ---> FILE: {$fileName}, ERROR: {$e->getMessage()}");
        }
    }

    private function putFileToTempDirectory($imageId)
    {
        if (empty($this->file['content'])) {
            Log::info("EMPTY-FILE--ID--{$this->movieId} ---> IMAGE-ID: {$imageId}");
            return false;
        }

        $relativePath = "/temp/{$this->movieId}/{$this->dirPosterName}/{$imageId}.jpg";
        $tempPath = Storage::disk('images')->path($relativePath);
        $directory = dirname($relativePath);

        try {
            if (!Storage::disk('images')->exists($directory)) {
                Storage::disk('images')->makeDirectory($directory, 0755, true, true);
                Log::info("CREATED-DIRECTORY--ID--{$this->movieId} ---> PATH: {$directory}");
            }
        } catch (\Exception $e) {
            Log::error("FAILED-CREATE-DIRECTORY--ID--{$this->movieId} ---> PATH: {$directory}, ERROR: {$e->getMessage()}");
            return false;
        }

        $absoluteDirPath = Storage::disk('images')->path($directory);
        if (!is_writable($absoluteDirPath)) {
            Log::error("DIR-NOT-WRITABLE--ID--{$this->movieId} ---> PATH: {$absoluteDirPath}");
            return false;
        }

        $maxRetries = 3;
        $retryCount = 0;
        $success = false;

        while ($retryCount < $maxRetries && !$success) {
            try {
                $result = Storage::disk('images')->put($relativePath, $this->file['content']);
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
        Log::debug("GET-FILES-BATCH-START--ID--{$this->movieId} ---> URL-COUNT: " . count($urls) . ", ID-COUNT: " . count($ids));
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
                    Log::debug("BATCH-REQUEST--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$url}");
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
                Log::debug("BATCH-FULFILLED--ID--{$this->movieId} ---> INDEX: {$index}, URL: {$urls[$index]}, STATUS: {$response->getStatusCode()}");
                if ($response->getStatusCode() === 200) {
                    $content = $response->getBody()->getContents();
                    $expectedSize = $response->hasHeader('Content-Length') ? (int) $response->getHeaderLine('Content-Length') : null;
                    $results[$ids[$index]] = [
                        'content' => $content,
                        'expectedSize' => $expectedSize,
                        'lastUrl' => $urls[$index],
                    ];
                    Log::debug("BATCH-SUCCESS--ID--{$this->movieId} ---> INDEX: {$index}, ID: {$ids[$index]}, CONTENT-LENGTH: " . strlen($content));
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

        Log::debug("GET-FILES-BATCH-COMPLETE--ID--{$this->movieId} ---> RESULTS: " . json_encode(array_keys($results)));
        return $results;
    }

    private function makeResizeDirectory($dirSizeName)
    {
        foreach ($dirSizeName as $sizeName) {
            Storage::disk('images')->makeDirectory("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/{$sizeName}/");
        }
    }

    private function deleteTempDirectory()
    {
        Storage::disk('images')->deleteDirectory('/temp');
    }
}
