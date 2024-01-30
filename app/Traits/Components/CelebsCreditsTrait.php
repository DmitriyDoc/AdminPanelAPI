<?php

namespace App\Traits\Components;

use DiDom\Document;
use DiDom\Query;
use Illuminate\Support\Facades\Log;

trait CelebsCreditsTrait
{
    protected function credits($pages){
        if (!empty($pages)) {
            foreach ($pages as $url => $page) {
                if (!empty($page)){
                    $document = new Document(trim($page));
                    $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                    $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                    if ($document->has('#filmography')) {
                        //ID ACTOR
                        $insertData[$this->signByField] = get_id_from_url($url,self::ACTOR_PATTERN)??null;
                        //FILMOGRAPHY
                        foreach ($this->occupations as $occupation){
                            if ($document->has("div[data-category='{$occupation}']")){
                                $container = $document->find("div[data-category='{$occupation}']")[0];
                                foreach ($container->nextSibling('div.filmo-category-section')->find('.filmo-row') as $item){
                                    $movieID = get_id_from_url($item->find('b a')[0]->attr('href')??null,self::ID_PATTERN);
                                    $title = $item->find('b')[0]->text()??null;

                                    if ($year = $item->find('span.year_column')[0]->text()){
                                        $str = str_replace('&nbsp;', ' ', htmlentities($year));
                                        $new = trim(html_entity_decode($str));
                                    }

                                    $item->firstInDocument('b')->remove();
                                    $item->firstInDocument('span')->remove();

                                    $expRoleId = $occupation.'-'.$movieID??'';
                                    $role = $item->find("//div[@id='{$expRoleId}']/br/following-sibling::text()[1]",Query::TYPE_XPATH)[0]??null;

                                    $insertData['filmography'][$occupation][$movieID]['role'] = $role?trim($role):null;
                                    $insertData['filmography'][$occupation][$movieID]['year'] = !empty($new) ? $new : null;
                                    $insertData['filmography'][$occupation][$movieID]['title'] = $title;
                                }
                            }
                        }
                        if (!empty($insertData['filmography'])){
                            $insertData['filmography'] = json_encode($insertData['filmography'],JSON_UNESCAPED_UNICODE)??null;
                        }
                        $this->updateOrInsert($this->update_info_table,$insertData,$this->signByField);
                        unset($insertData);
                    }
                }
            }
        }
    }
}
