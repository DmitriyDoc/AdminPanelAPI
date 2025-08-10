<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

trait IdImagesTrait
{
    protected function getIdImages($pages,$pattern)
    {
        if (!empty($pages)) {
            foreach ($pages as $url => $page) {
                if (!empty($page)){
                    $document = new Document(trim($page));
                    $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                    $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                    if ($document->has('section[data-testid=sub-section-images]')) {
                        $currentMovieId = get_id_from_url($url,$pattern);
                        $this->pushToRedis($document,$currentMovieId);
                    }
                }
            }
        }
    }

    private function pushToRedis($document,$id){
        if ($container = $document->find('section[data-testid=sub-section-images] div a::attr(href)')) {
            foreach ($container as $element) {
                if ($imgId = get_id_from_url($element,self::ID_IMG_PATTERN)){
                    Redis::rPush($id,$imgId);
                }
            }
        }

    }
}
