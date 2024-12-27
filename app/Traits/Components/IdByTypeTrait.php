<?php

namespace App\Traits\Components;


use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait IdByTypeTrait
{
    private function getIdByType($params=[])
    {
        if (!empty($this->urls)){
            foreach ($this->urls as $url){
                $document = new Document($url, true);
                if ($document->has('ul.ipc-metadata-list--dividers-between')) {
                    $list = $document->find('ul.ipc-metadata-list--dividers-between')[0];
                    $posts = $list->find('a.ipc-title-link-wrapper');
                    $arrayID = [];

                    foreach ($posts as $k =>$post) {
                        if ($this->flagType){
                            if ($idMovie = get_id_from_url($post->attr('href')??null,self::ID_PATTERN)){
                                array_push($arrayID,$idMovie);
                            }
                        } else {
                            if ($idActor =  get_id_from_url($post->attr('href')??null,self::ACTOR_PATTERN)){
                                if ($this->flagNewUpdate){
                                    if (!$this->checkExists($this->insert_id_table,$idActor)) {
                                        array_push($arrayID,[
                                            'actor_id' => $idActor,
                                            //'name' => clear_string_from_digits($post->text(),self::CLEAR_DIGITS_PATTERN)
                                        ]);
                                    }
                                } else {
                                    array_push($arrayID,[
                                        'actor_id' => $idActor,
                                        //'name' => clear_string_from_digits($post->text(),self::CLEAR_DIGITS_PATTERN)
                                    ]);
                                }
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

                    if (!$this->flagType){
                        foreach ($arrayID as $id) {
                            array_push($this->idCeleb,$id['actor_id']);
                        }
                        $parserUpdateCeleb = new ParserUpdateCelebController();
                        $parserUpdateCeleb->parseCelebs($this->typeImages,$this->idCeleb);
                    } else {
                        $arrayID = array_unique($arrayID);
                        $parserUpdateMovie = new ParserUpdateMovieController();
                        $parserUpdateMovie->parseMovies($params,array_unique($arrayID));
                    }
//                    if ($this->insert_id_table == 'celebs_id'){


//                    } else {
//                        $this->insertDB($this->insert_id_table,$insertData);
//                    }

//                    if ($list->count('a.ipc-title-link-wrapper') < 25){
//                        break;
//                    }

                }
            }
            $this->urls = [];
        }
    }

}
