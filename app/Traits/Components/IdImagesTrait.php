<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait IdImagesTrait
{
    private $imagesId = [];

    protected function getIdImages($pages,$updateTable,$pattern)
    {
        if (!empty($pages)) {
            foreach ($pages as $url => $page) {
                if (!empty($page)){
                    $document = new Document(trim($page));
                    $currentMovieId = get_id_from_url($url,$pattern);
                    $mergeIds = [];
                    $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                    $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                    if ($document->has('section[data-testid=sub-section-images]')) {
                        //GET IDS
                        $this->setImagesId($document);
                        $currentUpdateTable =  DB::table($updateTable)->where('id_movie',$currentMovieId)->get('id_images')->toArray();
                        $currentUpdateTable = (array)$currentUpdateTable[0];
                        if ($currentUpdateTable['id_images']){
                            $currentIds = json_decode($currentUpdateTable['id_images'],true);
                            $mergeIds = array_merge($currentIds, $this->imagesId);
                            $resultIds = array_unique($mergeIds);
                        }
                        $insertData['id_images'] = $mergeIds ? json_encode($resultIds,JSON_UNESCAPED_UNICODE) : $this->imagesId??null;
                    }
                    $insertData[$this->signByField] = $currentMovieId??null;

                    $this->updateOrInsert($updateTable,$insertData,$this->signByField);
                    $this->imagesId = [];
                    unset($insertData);
                }
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
