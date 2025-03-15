<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

trait IdImagesTrait
{
    private $imagesId = [];

    protected function getIdImages($pages,$pattern)
    {
        if (!empty($pages)) {
            $insertData = [];
            foreach ($pages as $url => $page) {
                if (!empty($page)){
                    $document = new Document(trim($page));
                    $currentMovieId = get_id_from_url($url,$pattern);
                    $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                    $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                    if ($document->has('section[data-testid=sub-section-images]')) {
                        $this->setImagesId($document);
                        $insertData[$currentMovieId] = $this->imagesId;
                    }
                    $this->imagesId = [];
                }
            }
            if (!empty($insertData)) {
                Redis::set('pictures_ids_data',json_encode($insertData,JSON_UNESCAPED_UNICODE));
            }
        }
    }

    private function setImagesId($document){
        if ($container = $document->find('section[data-testid=sub-section-images] div a::attr(href)')) {
            foreach ($container as $element) {
                if ($imgId = get_id_from_url($element,self::ID_IMG_PATTERN)){
                    array_push($this->imagesId, $imgId);
                }
            }
        }

    }
}
