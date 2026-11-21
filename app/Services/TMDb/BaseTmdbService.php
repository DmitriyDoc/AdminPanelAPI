<?php

namespace App\Services\TMDb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseTmdbService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.themoviedb.org/3';
    protected string $imageUrl; // Инициализируется в конструкторе

    public function __construct()
    {
        $this->apiKey = env('TMDB_TOKEN');
        // Безопасная инициализация свойства класса через .env с fallback
        $this->imageUrl = env('TMDB_IMAGE_BASE_URL').'/t/p';
    }

    /**
     * Формирует полный URL для изображения по его file_path
     */
    protected function buildFullUrl(string $filePath): string
    {
        return $this->imageUrl . '/original' . $filePath;
    }

    /**
     * Формирует URL нужного размера (w500, w1280, original и т.д.)
     */
    protected function buildSizedUrl(string $filePath, string $size = 'original'): string
    {
        return "{$this->imageUrl}/{$size}{$filePath}";
    }

    protected function makeRequest(string $endpoint, array $params = []): array
    {
        $params['api_key'] = $this->apiKey;
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $response = Http::timeout(30)
            ->retry(3,1000)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
            ->withoutVerifying()
            ->withOptions([
                'proxy' => '',
                'noproxy' => 'api.themoviedb.org,themoviedb.org,image.tmdb.org'
            ])
            ->get($url);

        if ($response->failed()) {
            $errorMsg = "TMDB API Error ({$response->status()}): " . substr($response->body(), 0, 300);
            Log::error($errorMsg, ['url' => $url]);
            throw new \Exception($errorMsg);
        }

        $data = $response->json();
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Ошибка: " . json_last_error_msg());
        }

        return $data;
    }
}
