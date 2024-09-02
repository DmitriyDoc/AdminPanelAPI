<?php

namespace App\Traits\Components;


use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait IdByTypeTrait
{
    private function getIdByType()
    {
        if (!empty($this->urls)){
            foreach ($this->urls as $url){
                $document = new Document($url, true);
                if ($document->has('ul.ipc-metadata-list--dividers-between')) {
                    $list = $document->find('ul.ipc-metadata-list--dividers-between')[0];
                    $posts = $list->find('a.ipc-title-link-wrapper');
                    $insertData = [];

                    foreach ($posts as $k =>$post) {
                        if ($this->flagType){
                            if ($idMovie = get_id_from_url($post->attr('href')??null,self::ID_PATTERN)){
                                array_push($insertData,[
                                    'id_movie' => $idMovie,
                                    'title' => clear_string_from_digits($post->text(),self::CLEAR_DIGITS_PATTERN)
                                ]);
                            }
                        } else {
                            if ($idActor =  get_id_from_url($post->attr('href')??null,self::ACTOR_PATTERN)){
                                array_push($insertData,[
                                    'actor_id' => $idActor,
                                    'name' => clear_string_from_digits($post->text(),self::CLEAR_DIGITS_PATTERN)
                                ]);
                            }
                        }
                    }
//            if ($document->has('a.lister-page-next')) {
//                $nextUrls = $document->find('.lister-page-next');
//                //Log::info("Next url ID {$this->titleType}-->",[$nextUrls[0]->attr('href')??null]);
//                Log::info("Next url ID -->",[$nextUrls[0]->attr('href')??null]);
//                $this->url = $this->domen.$nextUrls[0]->attr('href')??null;
//            } else {
//                $this->url = null;
//            }

                    $this->insertDB($this->insert_id_table,$insertData);

                    if ($list->count('a.ipc-title-link-wrapper') < 25){
                        break;
                    }

                }
            }
            $this->urls = [];
        }

    }
}
