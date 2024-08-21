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

                    if ($document->has("div[data-testid='Filmography']")) {

                        //ID ACTOR
                        $insertData[$this->signByField] = get_id_from_url($url,self::ACTOR_PATTERN)??null;

                        //FILMOGRAPHY
                        foreach ($this->occupations as $k => $occupation){
                            if ($document->has("h3")){
                                $h3Array = $document->find("h3");
                                foreach ($h3Array as $h3){
                                    if ($h3->text() == ucfirst($occupation)){
                                        $container = $document->find("#accordion-item-".$occupation."-previous-projects")[0];
                                        if (!empty($container)){
                                            $ul = $container->find('ul li.ipc-metadata-list-summary-item');
                                            if (!empty($ul)){
                                                foreach ($ul as $li){
                                                    $div = $li->children();
                                                    if (!empty($div[1])){
                                                        $a = $div[1]->firstChild()->first('a');
                                                        $ul = $div[1]->firstChild()->first('ul');
                                                        $span = $div[1]->lastChild()->first('ul li span.ipc-metadata-list-summary-item__li');
                                                        if ($a->hasAttribute('href')){
                                                            $movieID =  get_id_from_url($a->attr('href'),self::ID_PATTERN);
                                                            $title = $a->text() ?? null;
                                                        }
                                                        if (!empty($span)){
                                                            $str = str_replace('&nbsp;', ' ', htmlentities( $span->text() ?? null));
                                                            $year = trim(html_entity_decode($str));
                                                        }
                                                        if (!empty($ul)){
                                                            $role = $ul->text() ?? null;
                                                        }
                                                        if (!empty($movieID)){
                                                            $insertData['filmography'][$occupation][$movieID]['role'] = $role ? trim($role) : null;
                                                            $insertData['filmography'][$occupation][$movieID]['year'] = $year ?? null;
                                                            $insertData['filmography'][$occupation][$movieID]['title'] = $title ? trim($title) : null;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                    }
                                }
                            }
                        }
                        if (!empty($insertData['filmography'])){
                            $currentData = $this->selectDB($this->update_info_table, $insertData[$this->signByField], $this->signByField,'filmography');
                            if (!empty($currentData[0])){
                                $res = array_replace_recursive(json_decode($currentData[0],true),$insertData['filmography']);
                                $insertData['filmography'] = json_encode($res);
                                $this->updateOrInsert($this->update_info_table,$insertData,$this->signByField);
                                unset($res);
                                unset($insertData);
                            }
                        }
                    }
                }
            }
        }
    }
}
