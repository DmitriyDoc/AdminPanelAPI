<?php

namespace App\Traits\Components;

use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait CelebsInfoTrait
{
    protected function getCelebsInfo($pages)
    {
        if (!empty($pages)) {
            foreach ($pages as $url => $page) {
                $document = new Document(trim($page));
                $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                if ($document->has('main h1')) {
                    //ID ACTOR
                    $insertData[$this->signByField] = get_id_from_url($url,self::ACTOR_PATTERN)??null;
                    //NAME
                    $insertData['nameActor'] = $document->find('h1')[0]->child(0)->text()??null;
                    //PHOTO
                    if ($sections = $document->find('section.ipc-page-background>section.ipc-page-background')) {
                        if ($sections[0]->has('img')){
                            $insertData['photo'] = $sections[0]->find('img')[0]->attr('src')??null;
                        }
                    }
                    //KNOWN_FOR
                    if ($document->has('div[data-testid=nm_flmg_kwn_for]')){
                        if ($knowForContainer = $document->find('div[data-testid=nm_flmg_kwn_for] div[data-testid=shoveler-items-container]')[0]){
                            $knowFor = [];
                            foreach ($knowForContainer->find('div[data-testid^=nm_kwn_for_]') as $elem) {
                                array_push($knowFor,get_id_from_url($elem->find('a::attr(href)')[0],self::ID_PATTERN)??null);
                            }
                            $insertData['knowfor'] = json_encode($knowFor,JSON_UNESCAPED_UNICODE)??null;
                            unset($knowFor);
                        }
                    }
                    //BIRTHDAY, BIRTHDAY LOCATION
                    if ($document->has('li[data-testid=nm_pd_bl]')) {
                        if ($elementsBirthday = $document->find('li[data-testid=nm_pd_bl]')[0]->find('ul')[0])
                            $date = str_replace(",","",$elementsBirthday->firstChild()->text());
                        $insertData['birthday'] = date('Y-m-d', strtotime($date))??null;
                        if ($elementsBirthday->count('li') == 2){
                            $insertData['birthdayLocation'] = $elementsBirthday->lastChild()->text()??null;
                        }
                    }
                    //DIED, DIE LOCATION
                    if ($document->has('li[data-testid=nm_pd_dl]')) {
                        if ($elementsBirthday = $document->find('li[data-testid=nm_pd_dl]')[0]->find('ul')[0])
                            $date = str_replace(",","",$elementsBirthday->firstChild()->text());
                        $insertData['died'] = date('Y-m-d', strtotime($date))??null;
                        if ($elementsBirthday->count('li') == 2){
                            $insertData['dieLocation'] = $elementsBirthday->lastChild()->find('a')[0]->text()??null;
                        }
                    }

                    $this->updateOrInsert( $this->update_info_table,$insertData,$this->signByField);
                    $this->insertDB('celebs_id',[
                        'actor_id' => $insertData[$this->signByField] ?? null,
                        'name' => $insertData['nameActor'] ?? null,
                    ]);
                    unset($insertData);
                }
            }
        }
    }
}
