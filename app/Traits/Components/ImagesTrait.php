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
            foreach (array_chunk($links, 5) as $chunk) {
                usleep(rand(1000000, 2000000));
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
