<?php

namespace App\Services\Resize;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaApiUploader
{
    private const RESIZED_DISK = 'resized';
    private const MAX_RETRIES = 3;

    public function uploadBatch(array $batchFiles, int $typeFilmId, string $hashedId): bool
    {
        if (empty($batchFiles)) return true; // Пустой массив считаем успехом

        $retryCount = 0;
        $success = false;

        while ($retryCount < self::MAX_RETRIES && !$success) {
            try {
                $multipart = $this->buildMultipartPayload($batchFiles);

                if (empty($multipart)) {
                    Log::warning("EMPTY-MULTIPART--HASHED--{$hashedId} ---> No valid files to upload.");
                    return false; // 🔥 Возвращаем false
                }

                $url = env('API_HOST_URL') . "/api/images/batch/{$typeFilmId}/{$hashedId}";

                $response = Http::timeout(120)
                    ->withToken(env('API_TOKEN'))
                    ->asMultipart()
                    ->post($url, $multipart);

                if ($response->successful()) {
                    $success = true;
                    $this->cleanupResizedFiles($batchFiles);
                    //Log::info("BATCH-UPLOADED--HASHED--{$hashedId} ---> FILES: " . count($batchFiles));
                } elseif ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After', 10);
                    Log::warning("RATE-LIMITED--HASHED--{$hashedId} ---> Retrying after {$retryAfter}s");
                    sleep($retryAfter);
                    $retryCount++;
                } else {
                    Log::error("UPLOAD-FAILED--HASHED--{$hashedId} ---> STATUS: {$response->status()}, BODY: " . substr($response->body(), 0, 200));
                    $retryCount++;
                    sleep(5);
                }
            } catch (\Exception $e) {
                Log::error("UPLOAD-EXCEPTION--HASHED--{$hashedId} ---> ERROR: {$e->getMessage()}");
                $retryCount++;
                sleep(5);
            }
        }

        return $success; // 🔥 Возвращаем итоговый результат
    }

    private function buildMultipartPayload(array $batchFiles): array
    {
        $multipart = [];
        $fileIndex = 0;

        foreach ($batchFiles as $batch) {
            foreach ($batch['paths'] as $size => $path) {
                if (Storage::disk(self::RESIZED_DISK)->exists($path)) {
                    $filePath = Storage::disk(self::RESIZED_DISK)->path($path);
                    $multipart[] = [
                        'name' => "images[{$fileIndex}]",
                        'contents' => fopen($filePath, 'r'), // fopen экономит память
                        'filename' => basename($path),
                    ];
                    $multipart[] = [
                        'name' => "images[{$fileIndex}][size]",
                        'contents' => $size,
                    ];
                    $multipart[] = [
                        'name' => "images[{$fileIndex}][dirPosterName]",
                        'contents' => $batch['dirPosterName'],
                    ];
                    $fileIndex++;
                } else {
                    Log::error("FILE-MISSING-ON-DISK ---> PATH: {$path}");
                }
            }
        }

        return $multipart;
    }

    private function cleanupResizedFiles(array $batchFiles): void
    {
        foreach ($batchFiles as $batch) {
            foreach ($batch['paths'] as $path) {
                Storage::disk(self::RESIZED_DISK)->delete($path);
            }
        }
    }
}
