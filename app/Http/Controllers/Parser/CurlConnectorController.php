<?php

namespace App\Http\Controllers\Parser;

use Illuminate\Support\Facades\Log;

class CurlConnectorController
{
    const HEADERS = ['cache-control: max-age=0',
        'upgrade-insecure-requests: 1',
        'user-agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36',
        'sec-fetch-user: ?1',
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
        'x-compress: null',
        'sec-fetch-site: none',
        'sec-fetch-mode: navigate',
        'accept-encoding: utf-8, br',
        'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
    ];
    private $referer = "https://google.com/";

    public function getCurlMulty($urls)
    {
        $mh = curl_multi_init();
        foreach ($urls as $i => $url) {
            $conn[$i]=curl_init($url);
            curl_setopt($conn[$i], CURLOPT_HTTPHEADER, self::HEADERS);
            curl_setopt($conn[$i], CURLOPT_URL, $url);
            curl_setopt($conn[$i], CURLOPT_REFERER, $this->referer);
            curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($conn[$i], CURLOPT_BINARYTRANSFER, true);
            curl_setopt($conn[$i], CURLOPT_CONNECTTIMEOUT, 0);
            //curl_setopt($conn[$i], CURLOPT_TIMEOUT, 30);
            curl_setopt($conn[$i], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($conn[$i], CURLOPT_HEADER, false);
            curl_setopt($conn[$i], CURLOPT_FRESH_CONNECT, true);

            curl_multi_add_handle ($mh,$conn[$i]);
        }
        do { curl_multi_exec($mh,$active); } while ($active);
        for ($i = 0; $i < count($urls); $i++) {
            //ответ сервера в переменную
            $res[$urls[$i]] = curl_multi_getcontent($conn[$i]);
            curl_multi_remove_handle($mh, $conn[$i]);
            curl_close($conn[$i]);
        }
        curl_multi_close($mh);
        return $res;
    }

//    public function getCurlPage($url)
//    {
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_HTTPHEADER, self::HEADERS);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_REFERER, $this->referer);
//        curl_setopt($ch, CURLOPT_HEADER, 1);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        $response = curl_exec($ch);
//        curl_close($ch);
//
//        return $response;
//    }

}
