<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait ImagesTrait
{
    protected function getImages($linksArray,$updateTable){
        foreach($linksArray as $id => $links) {
            $this->deleteById($updateTable,$this->signByField,$id);
            foreach (array_chunk($links, 30) as $chunk) {
                $pages = [];
                $pages[] = (new CurlConnectorController())->getCurlMulty($chunk);
                foreach ($pages[0] as $link => $page) {
                    if (!empty($page)) {
                        $document = new Document(trim($page));
                        $this->logErrors($document, "div[class=error_code_404]", " 404-->>", $link);
                        $this->logErrors($document, "div[class=errorPage__container]", " 500-->>", $link);
                        if ($document->has('div[data-testid=media-viewer]')) {
                            $imgId = get_id_from_url($link,self::ID_IMG_PATTERN)??null;
                            $insertData['src'] = $document->find('img[data-image-id='.$imgId.'-curr]::attr(src)')[0]??null;
                            $insertData['srcset'] = $document->find('img[data-image-id='.$imgId.'-curr]::attr(srcset)')[0]??null;
//                            if ($document->find('div.media-viewer__media-sheet div.sc-9422afe0-10')[0]->count('div.sc-9422afe0-14') > 1) {
//                                $celebsNames = $document->find('div.media-viewer__media-sheet div.sc-9422afe0-10')[0]->firstChild();
//                                if ($celebsNames->has('span a.ipc-link--baseAlt')) {
//                                    $namesContainer = $celebsNames->find('span a.ipc-link--baseAlt');
//                                    foreach ($namesContainer as $name) {
//                                        $href = $name->attr('href');
//                                        $clearId = get_id_from_url($href,self::ACTOR_PATTERN);
//                                        if (!empty($clearId)){
//                                            $celebsNamesArray[][$clearId] = html_entity_decode($name->text());
//                                        }
//                                    }
//                                    if (!empty($celebsNamesArray)){
//                                        $insertData['namesCelebsImg'] = json_encode($celebsNamesArray,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)??null;
//                                        unset($celebsNamesArray);
//                                    }
//                                }
//                            }
                        }
                        $insertData[$this->signByField] = $id;
                        $this->insertDB($updateTable,$insertData);
                        unset($insertData);
                    }
                }
            }
        }
    }
}
