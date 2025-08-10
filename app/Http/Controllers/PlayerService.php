<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlayerService
{
    public function getAllohaPlayer(array $params): array
    {
        $result = [
            'source' => 'Alloha',
            'iframeUrl' => '',
            'translations' => [],
            'success' => false,
            'updatedAt' => [],
            'name' => '',
            'original_name' => '',
            'year' => null,
            'poster' => '',
            'description' => ''
        ];

        if (empty($params['imdb'])) {
            Log::warning('IMDb ID не предоставлен');
            return $result;
        }

        $baseLink = 'https://api.alloha.tv/';
        $token = env('ALLOHA_TOKEN');

        try {
            $response = Http::get($baseLink, [
                'token' => $token,
                'imdb' => $params['imdb']
            ]);
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            Log::info('Alloha API request:', [
                'url' => $baseLink . '?token=' . $token . '&imdb=' . $params['imdb'],
                'status' => $response->status(),
                'body' => substr($body, 0, 500) // Логируем первые 500 символов ответа
            ]);

            if (!is_array($data) || !isset($data['status'])) {
                Log::error('Alloha API вернул некорректный формат данных: ' . $body);
                throw new \Exception('API вернул некорректный формат данных (ожидался JSON с ключом "status")', 500);
            }

            if ($data['status'] === 'success' && isset($data['data']['iframe'])) {
                $result = [
                    'source' => 'Alloha',
                    'iframeUrl' => $data['data']['iframe'],
                    'success' => true,
                    'updatedAt' => [],
                    'name' => $data['data']['name'] ?? '',
                    'original_name' => $data['data']['original_name'] ?? '',
                    'year' => $data['data']['year'] ?? null,
                    'poster' => $data['data']['poster'] ?? '',
                    'description' => $data['data']['description'] ?? ''
                ];
            } else {
                Log::warning('Alloha API вернул статус "' . ($data['status'] ?? 'unknown') . '" или данные плеера отсутствуют: ' . json_encode($data));
                throw new \Exception('Фильм не найден или API вернул некорректные данные', 404);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Ошибка при запросе к Alloha API: ' . $e->getMessage());
            throw new \Exception('Ошибка при получении данных от Alloha: ' . $e->getMessage(), 500);
        }
    }
}
