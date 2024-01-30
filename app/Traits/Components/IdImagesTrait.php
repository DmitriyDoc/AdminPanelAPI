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
                    if ($document->has('div[id=media_index_thumbnail_grid]')) {
                        //GET IDS
                        $this->setImagesId($document);
                        //GET PAGES
                        if ($document->has('.page_list')) {
                            $pages = $document->find('.page_list')[0];
                            $pages = $pages->count(' a');
                            for ($i=2;$i<=$pages+1;$i++) {
                                if ($i<=100){
                                    $arrPages[] = $url.'&page='.$i;
                                }
                            }
                            $chunksPages = array_chunk($arrPages, 30);
                            unset($arrPages);
                            foreach ($chunksPages as $chunk){
                                $pagesIds[] = (new CurlConnectorController())->getCurlMulty($chunk);
                                foreach ($pagesIds[0] as $urlId => $pageId) {
                                    if (!empty($pageId) ) {
                                        $document = new Document(trim($pageId));
                                        $this->logErrors($document,"div[class=error_code_404]"," 404--PAGES-->>",$url);
                                        $this->logErrors($document,"div[class=errorPage__container]"," 500--PAGES-->>",$url);
                                        //GET IDS
                                        $this->setImagesId($document);
                                    }
                                }
                                unset($pagesIds);
                            }
                        }
                        $insertData['id_images'] = json_encode($this->imagesId,JSON_UNESCAPED_UNICODE)??null;
                    }
                    $insertData[$this->signByField] = get_id_from_url($url,$pattern)??null;

                    //Log::info('DATA-->',[$insertData??[]]);
                    $this->updateOrInsert($updateTable,$insertData,$this->signByField);
                    $this->imagesId = [];
                    unset($insertData);
                }
            }
        }
    }

    private function setImagesId($document){
        if ($container = $document->find('div[id=media_index_thumbnail_grid] a::attr(href)')) {
            foreach ($container as $element) {
                if ($imgId = get_id_from_url($element,self::ID_IMG_PATTERN)){
                    array_push($this->imagesId, $imgId);
                }
            }
        }
    }
}
