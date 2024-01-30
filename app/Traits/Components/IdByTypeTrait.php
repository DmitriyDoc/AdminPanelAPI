<?php

namespace App\Traits\Components;


use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait IdByTypeTrait
{
    private function getIdByType()
    {

        $document = new Document($this->url, true);

        if ($document->has('.ipc-title-link-wrapper')) {
            $posts = $document->find('.ipc-title-link-wrapper');
            $insertData = [];

            foreach ($posts as $post) {
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
            //$this->getIdByType();
        }
    }
}
