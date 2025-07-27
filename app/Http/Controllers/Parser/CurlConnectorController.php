<?php

namespace App\Http\Controllers\Parser;

use Illuminate\Support\Facades\Log;

class CurlConnectorController
{
    const HEADERS = [
        'cache-control: max-age=0',
        'upgrade-insecure-requests: 1',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'accept-encoding: gzip, deflate, br',
        'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        'sec-fetch-site: none',
        'sec-fetch-mode: navigate',
        'sec-fetch-user: ?1',
    ];

    private $referer = "https://www.google.com/";

    public function getCurlMulty($urls)
    {
        $mh = curl_multi_init();
        $conn = [];
        $res = [];

        foreach ($urls as $i => $url) {
            $conn[$i] = curl_init($url);
            curl_setopt($conn[$i], CURLOPT_HTTPHEADER, self::HEADERS);
            curl_setopt($conn[$i], CURLOPT_URL, $url);
            curl_setopt($conn[$i], CURLOPT_REFERER, $this->referer);
            curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($conn[$i], CURLOPT_BINARYTRANSFER, true);
            curl_setopt($conn[$i], CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($conn[$i], CURLOPT_TIMEOUT, 30);
            curl_setopt($conn[$i], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($conn[$i], CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($conn[$i], CURLOPT_HEADER, false);
            curl_setopt($conn[$i], CURLOPT_FRESH_CONNECT, true);
            curl_setopt($conn[$i], CURLOPT_ENCODING, 'gzip, deflate, br');

            curl_multi_add_handle($mh, $conn[$i]);
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($status > 0) {
                Log::error("cURL multi exec error: " . curl_multi_strerror($status));
            }
            curl_multi_select($mh);
        } while ($active && $status == CURLM_OK);

        foreach ($urls as $i => $url) {
            $response = curl_multi_getcontent($conn[$i]);
            $httpCode = curl_getinfo($conn[$i], CURLINFO_HTTP_CODE);

            if ($response === false || $httpCode !== 200) {
                Log::error("Failed to fetch URL: $url, HTTP Code: $httpCode, Error: " . curl_error($conn[$i]));
                $res[$url] = null;
            } else {
                $res[$url] = $response;
            }

            curl_multi_remove_handle($mh, $conn[$i]);
            curl_close($conn[$i]);
        }

        curl_multi_close($mh);
        return $res;
    }
}
