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
            $arrayID = [];
            foreach ($this->urls as $url){
                $document = new Document($url, true);
                if ($document->has('ul.ipc-metadata-list--dividers-between')) {
                    $list = $document->find('div.ipc-metadata-list-summary-item__c');
                    foreach ($list as $item) {
                        if ($item->has('div.ipc-media__img')){
                            $title = $item->find('a.ipc-title-link-wrapper')[0];
                            if ($this->flagType){
                                if ($idMovie = get_id_from_url($title->attr('href')??null,self::ID_PATTERN)){
                                    array_push($arrayID,$idMovie);
                                }
                            } else {
                                if ($idActor =  get_id_from_url($title->attr('href')??null,self::ACTOR_PATTERN)){
                                    if (!$this->flagNewUpdate){
                                        if (!$this->checkExists('celebs_info',$idActor)) {
                                            array_push($arrayID, $idActor);
                                        }
                                    } else {
                                        array_push($arrayID, $idActor);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->urls = [];
            if (!empty($arrayID)){
                $arrayID = array_unique($arrayID);
                if (!$this->flagType){
                    foreach ($arrayID as $id) {
                        array_push($this->idCeleb,$id);
                    }
                    $parserUpdateCeleb = new ParserUpdateCelebController();
                    $parserUpdateCeleb->parseCelebs($this->typeImages,$this->idCeleb);
                } else {
                    $parserUpdateMovie = new ParserUpdateMovieController();
                    $parserUpdateMovie->parseMovies($params,$arrayID);
                }
            }
        }
    }
}
