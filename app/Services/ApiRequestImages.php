<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiRequestImages
{
    /**
     * Send an API request with the specified method, URL, and data.
     *
     * @param string $url The API endpoint URL
     * @param string $method The HTTP method (GET, POST, DELETE)
     * @param array $data The request data (body for POST, query parameters for DELETE)
     * @param bool $useToken Whether to include the API token in the request
     * @return array The response with success status, HTTP status code, message, and data
     */
    public function sendApiRequest(string $url, string $method = 'GET', array $data = [], bool $useToken = false): array
    {
        try {
            $http = Http::withHeaders([
                'Accept' => 'application/json',
            ]);

            if ($useToken) {
                $token = env('API_TOKEN');
                if (empty($token)) {
                    Log::error("API-REQUEST-ERROR ---> URL: {$url}, METHOD: {$method}, ERROR: API_TOKEN is not set in environment");
                    return [
                        'success' => false,
                        'status' => 500,
                        'message' => 'API token is not configured',
                        'data' => null,
                    ];
                }
                $http = $http->withToken($token);
            }

            Log::debug("API-REQUEST-START ---> URL: {$url}, METHOD: {$method}, USE-TOKEN: " . ($useToken ? 'true' : 'false') . ", DATA: " . json_encode($data));

            switch (strtoupper($method)) {
                case 'POST':
                    $response = $http->post($url, $data);
                    break;
                case 'DELETE':
                    $response = $http->delete($url, $data);
                    break;
                case 'GET':
                default:
                    $response = $http->get($url, $data);
                    break;
            }

            if ($response->successful()) {
                $responseData = $response->json();
                Log::debug("API-REQUEST-SUCCESS ---> URL: {$url}, METHOD: {$method}, STATUS: {$response->status()}, RESPONSE: " . json_encode($responseData));
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $responseData,
                ];
            } elseif ($response->status() === 404) {
                $responseData = $response->json();
                Log::warning("API-REQUEST-NOT-FOUND ---> URL: {$url}, METHOD: {$method}, STATUS: {$response->status()}, RESPONSE: " . json_encode($responseData));
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Resource not found',
                    'data' => $responseData,
                ];
            } elseif ($response->status() === 401) {
                Log::warning("API-REQUEST-UNAUTHORIZED ---> URL: {$url}, METHOD: {$method}, STATUS: {$response->status()}, RESPONSE: " . json_encode($response->body()));
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Unauthorized access',
                    'data' => null,
                ];
            } else {
                Log::error("API-REQUEST-FAILED ---> URL: {$url}, METHOD: {$method}, STATUS: {$response->status()}, RESPONSE: " . json_encode($response->body()));
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'message' => 'Request failed',
                    'data' => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            Log::error("API-REQUEST-EXCEPTION ---> URL: {$url}, METHOD: {$method}, ERROR: {$e->getMessage()}");
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Internal server error: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Clear images by sending a DELETE request to the /api/images/clear endpoint.
     *
     * @param string $typeId The type ID (e.g., movie type)
     * @param string $movieId The movie ID
     * @param string $dirPosterName The directory name for posters or images
     * @return array The response from the API
     */
    public function clearImages(string $typeId, string $movieId, string $dirPosterName): array
    {
        $url = env('API_HOST_URL') . '/api/images/clear';
        $data = [
            'type_id' => $typeId,
            'movie_id' => $movieId,
            'dirPosterName' => $dirPosterName,
        ];

        return $this->sendApiRequest($url, 'DELETE', $data, true);
    }
}
