<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
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
                    $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                    $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                    if ($document->has('section[data-testid=sub-section-images]')) {
                        //GET IDS
                        $this->setImagesId($document);
                        $insertData['id_images'] = json_encode($this->imagesId,JSON_UNESCAPED_UNICODE)??null;
                    }
                    $insertData[$this->signByField] = get_id_from_url($url,$pattern)??null;
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
