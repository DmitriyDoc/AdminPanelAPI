<?php

namespace App\Services\Resize;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;

class ImageProcessor
{
    private const TEMP_DISK = 'temp';
    private const RESIZED_DISK = 'resized';
    private const MAX_RETRIES = 3;
    private const TIMEOUT = 90;
    private const CONNECT_TIMEOUT = 20;
    // В методе processPoster замените возврат на массив с флагом успеха:
    public function processPoster(int $id, string $url, string $hashedId, int $typeFilmId, string $dirName): array
    {
        $content = $this->downloadImage($url);
        if (!$content) {
            // 🔥 Возвращаем конкретную причину: скачивание
            return ['success' => false, 'reason' => 'download'];
        }

        $tempPath = "{$hashedId}/{$dirName}/{$id}.jpg";
        Storage::disk(self::TEMP_DISK)->put($tempPath, $content);
        $localTempPath = Storage::disk(self::TEMP_DISK)->path($tempPath);

        try {
            $image = Image::read($localTempPath);
            $paths = [];

            if ($dirName === 'wallpaper') {
                $paths = $this->resizeWallpaper($image, $typeFilmId, $hashedId, $dirName, $id);
            } elseif (in_array($dirName, ['original_poster', 'russian_poster'])) {
                $rgbSuffix = $this->calculateAverageRGB($localTempPath);
                $actualFileName = $id . $rgbSuffix;
                $paths = $this->resizeMainPoster($image, $typeFilmId, $hashedId, $dirName, $id, $actualFileName);
            } else {
                $paths = $this->resizeSecondaryPoster($image, $typeFilmId, $hashedId, $dirName, $id);
            }

            unset($image);
            // 🔥 Возвращаем успех и пути
            return ['success' => true, 'paths' => $paths];

        } catch (\Exception $e) {
            Log::error("PROCESS-ERROR--HASHED--{$hashedId} ---> FILE: {$id}, ERROR: {$e->getMessage()}");
            // 🔥 Возвращаем конкретную причину: обработка
            return ['success' => false, 'reason' => 'process'];
        } finally {
            Storage::disk(self::TEMP_DISK)->delete($tempPath);
        }
    }

// В методе processBackdrop сделайте аналогично:
    public function processBackdrop(int $id, string $url, string $hashedId, int $typeFilmId): array
    {
        $content = $this->downloadImage($url);
        if (!$content) {
            return ['success' => false, 'reason' => 'download'];
        }

        $tempPath = "{$hashedId}/images/{$id}.jpg";
        Storage::disk(self::TEMP_DISK)->put($tempPath, $content);
        $localTempPath = Storage::disk(self::TEMP_DISK)->path($tempPath);

        try {
            $image = Image::read($localTempPath);
            $paths = $this->resizeBackdrop($image, $typeFilmId, $hashedId, $id);
            unset($image);
            return ['success' => true, 'paths' => $paths];
        } catch (\Exception $e) {
            Log::error("PROCESS-BACKDROP-ERROR--HASHED--{$hashedId} ---> FILE: {$id}, ERROR: {$e->getMessage()}");
            return ['success' => false, 'reason' => 'process'];
        } finally {
            Storage::disk(self::TEMP_DISK)->delete($tempPath);
        }
    }

    /**
     * 🔥 ОСНОВНЫЕ ПОСТЕРЫ (original_poster, russian_poster)
     * full_size получает имя с RGB суффиксом ($actualFileName).
     * medium и small получают обычное имя ($id.jpg).
     */
    private function resizeMainPoster($image, int $typeFilmId, string $hashedId, string $dirName, string $id, string $actualFileName): array
    {
        $paths = [];

        // 1. Full size (Используем $actualFileName с RGB суффиксом, качество 80)
        $fullClone = clone $image;
        $fullClone->scale(width: 600);
        $savePathFull = "/{$typeFilmId}/{$hashedId}/{$dirName}/full_size/{$actualFileName}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($savePathFull, $fullClone->encode(new JpegEncoder(quality: 80)));
        $paths['full_size'] = $savePathFull;
        unset($fullClone);

        // 2. Medium (Используем обычный $id, качество 70)
        $medClone = clone $image;
        $medClone->scale(width: 400);
        $medPath = "/{$typeFilmId}/{$hashedId}/{$dirName}/medium/{$id}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($medPath, $medClone->encode(new JpegEncoder(quality: 70)));
        $paths['medium'] = $medPath;
        unset($medClone);

        // 3. Small (Используем обычный $id, качество 70)
        $smallClone = clone $image;
        $smallClone->scale(width: 200);
        $smallPath = "/{$typeFilmId}/{$hashedId}/{$dirName}/small/{$id}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($smallPath, $smallClone->encode(new JpegEncoder(quality: 70)));
        $paths['small'] = $smallPath;
        unset($smallClone);

        return $paths;
    }

    /**
     * 🔥 ВТОРИЧНЫЕ ПОСТЕРЫ (characters, alt)
     * Все размеры получают обычное имя ($id.jpg).
     */
    private function resizeSecondaryPoster($image, int $typeFilmId, string $hashedId, string $dirName, int $fileId): array
    {
        $paths = [];

        // 1. Small (200px по ширине, качество 70)
        $smallClone = clone $image;
        $smallClone->scale(width: 200);
        $smallPath = "/{$typeFilmId}/{$hashedId}/{$dirName}/small/{$fileId}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($smallPath, $smallClone->encode(new JpegEncoder(quality: 70)));
        $paths['small'] = $smallPath;
        unset($smallClone);

        // 2. Full size (600px по ширине, качество 80)
        $fullClone = clone $image;
        $fullClone->scale(width: 600);
        $fullPath = "/{$typeFilmId}/{$hashedId}/{$dirName}/full_size/{$fileId}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($fullPath, $fullClone->encode(new JpegEncoder(quality: 80)));
        $paths['full_size'] = $fullPath;
        unset($fullClone);

        return $paths;
    }

    /**
     * 🔥 ОБОИ (wallpaper)
     */
    private function resizeWallpaper($image, int $typeFilmId, string $hashedId, string $dirName, int $fileId): array
    {
        //Log::debug("WALLPAPER-RESIZE-START ---> FILE: {$fileId}, ORIGINAL: {$image->width()}x{$image->height()}");

        $clone = clone $image;

        if ($clone->width() > 1000) {
            $clone->scale(width: 1000);
        }

        //Log::debug("WALLPAPER-RESIZED ---> FILE: {$fileId}, NEW: {$clone->width()}x{$clone->height()}");

        $savePath = "/{$typeFilmId}/{$hashedId}/{$dirName}/full_size/{$fileId}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($savePath, $clone->encode(new JpegEncoder(quality: 85)));

        return ['full_size' => $savePath];
    }

    /**
     * 🔥 БЕКДРОПЫ (images)
     */
    private function resizeBackdrop($image, int $typeFilmId, string $hashedId, int $fileId): array
    {
        $clone = clone $image;
        $quality = 80;

        if ($clone->width() > 1920) {
            $clone->scale(width: 1920);
        }

        if ($clone->height() > 3000) {
            $clone->scale(height: 3000);
            $quality = 75;
        }

        $savePath = "/{$typeFilmId}/{$hashedId}/images/full_size/{$fileId}.jpg";
        Storage::disk(self::RESIZED_DISK)->put($savePath, $clone->encode(new JpegEncoder(quality: $quality)));

        return ['full_size' => $savePath];
    }

    /**
     * 🔥 ВЫЧИСЛЕНИЕ СРЕДНЕГО RGB ЦВЕТА для нейминга файла
     */
    private function calculateAverageRGB(string $tempPath): string
    {
        try {
            $gdImage = @imagecreatefromjpeg($tempPath);
            if (!$gdImage) {
                $gdImage = @imagecreatefrompng($tempPath);
            }
            if (!$gdImage) {
                return '';
            }

            $width = imagesx($gdImage);
            $height = imagesy($gdImage);
            $step = max(1, (int)sqrt(($width * $height) / 10000));

            $totalR = $totalG = $totalB = $count = 0;

            for ($y = 0; $y < $height; $y += $step) {
                for ($x = 0; $x < $width; $x += $step) {
                    $rgb = imagecolorat($gdImage, $x, $y);
                    $totalR += ($rgb >> 16) & 0xFF;
                    $totalG += ($rgb >> 8) & 0xFF;
                    $totalB += $rgb & 0xFF;
                    $count++;
                }
            }

            imagedestroy($gdImage);

            $avgR = (int)round($totalR / $count);
            $avgG = (int)round($totalG / $count);
            $avgB = (int)round($totalB / $count);

            return "-{$avgR}-{$avgG}-{$avgB}";
        } catch (\Exception $e) {
            Log::warning("RGB-CALC-FAILED ---> PATH: {$tempPath}, ERROR: {$e->getMessage()}");
            return '';
        }
    }

    /**
     * 🔥 ЗАГРУЗКА ИЗОБРАЖЕНИЯ С РЕТРАЯМИ
     */
    private function downloadImage(string $url): ?string
    {
        $retryCount = 0;
        $backoff = [5, 10, 15];

        while ($retryCount < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(self::TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            // Дополнительно: отключаем проверку SSL, если в Docker проблемы с сертификатами (опционально, но безопасно для CDN)
                            // CURLOPT_SSL_VERIFYPEER => false,
                        ]
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'image/jpeg,image/png,image/gif,*/*;q=0.8',
                        'Referer' => 'https://image.tmdb.org/',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $contentType = $response->header('Content-Type');

                    if ($contentType === 'image/webp') {
                        Log::warning("WEBP-REJECTED--URL--{$url} ---> CDN ignored Accept header");
                        return null;
                    }

                    if (str_starts_with($contentType, 'image/')) {
                        return $response->body();
                    }
                }
            } catch (\Exception $e) {
                Log::warning("DOWNLOAD-FAILED-EXCEPTION ---> URL: {$url}, ERROR: {$e->getMessage()}, RETRY: {$retryCount}");
            }

            $retryCount++;
            if ($retryCount < self::MAX_RETRIES) {
                $sleepTime = $backoff[$retryCount - 1] ?? 10;
                sleep($sleepTime);
            }
        }

        Log::error("DOWNLOAD-FAILED-AFTER-RETRIES ---> URL: {$url}");
        return null;
    }
}
