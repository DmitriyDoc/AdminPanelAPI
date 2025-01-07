<?php


namespace App\Http\Controllers;


use App\Models\CelebsInfoRu;
use App\Models\MoviesInfoRu;
use \Dejurin\GoogleTranslateForFree;
use Illuminate\Support\Facades\Log;

class TranslatorController
{
    private $source = 'en';
    private $target = 'ru';
    private $attempts = 5;
    private $translator;
    private $localizingTable = null;
    private $columnId = null;
    private $columnKey = null;

    private $dataMovie = [
        'id_movie' => null,
        'genres' => null,
        'cast' => null,
        'directors' => null,
        'writers' => null,
        'story_line' => null,
        'countries' => null,
        'release_date' => null
    ];
    private $dataCeleb = [
        'id_celeb' => null,
        'filmography' => null,
        'nameActor' => null,
        'birthdayLocation' => null,
        'dieLocation' => null,
    ];
    public function __construct()
    {
        $this->translator = new GoogleTranslateForFree();
    }

    private function startTranslate( $inputText = null ) {
        return $this->translator->translate($this->source, $this->target, $inputText, $this->attempts) ?? null;
    }

    private function updateOrCreateLocalizing($data){
        $localizingModel = $this->localizingTable::where($this->columnKey,$this->columnId)->first();
        if ($localizingModel){
            $localizingModel::updateOrCreate([$this->columnKey=>$this->columnId],$data);
        } else {
            $this->localizingTable::firstOrCreate($data);
        }
    }
    public function translateTag($inputTag){
        return $this->startTranslate($inputTag);
    }
    public function translateMovie($dataInfo, $movieId, $columnKey) {
        $this->columnId = $movieId;
        $this->columnKey = $columnKey;
        $this->dataMovie['id_movie'] = $dataInfo->id_movie;
        $this->localizingTable = new MoviesInfoRu();
        $this->dataMovie['genres'] = null;
        if (!empty($dataInfo->genres)){
            $unpackGenres = json_decode($dataInfo->genres);
            $genres = array_unique($unpackGenres);
            $genres = json_encode($genres,JSON_UNESCAPED_UNICODE);
            $resultGenres = $this->startTranslate($genres);
            $resultGenres = str_replace(['«','»'] ,'"',$resultGenres);
            $this->dataMovie['genres'] = str_replace("\u{200B}" ,'',$resultGenres);
        }
        $this->dataMovie['cast'] = null;
        if (!empty($dataInfo->cast)){
            $castDecode = json_decode($dataInfo->cast, true);
            foreach ( $castDecode as $id => $item ) {
                $resultCast[$id]['role'] = $this->startTranslate($item['role']);
                $resultCast[$id]['actor'] = $this->startTranslate($item['actor']);
            }

            $resultCast = json_encode($resultCast ,JSON_UNESCAPED_UNICODE);
            $this->dataMovie['cast'] = json_validate($resultCast) ? $resultCast : null;
        }

        $this->dataMovie['directors'] = null;
        if (!empty($dataInfo->directors)){
            $resultDirectors = $this->startTranslate($dataInfo->directors);
            $resultDirectors = str_replace(['«','»'] ,'"',$resultDirectors);
            $resultDirectors = str_replace(':""' ,"\":\"\"",$resultDirectors);
            $resultDirectors = str_replace("\u{200B}" ,'',$resultDirectors);
            $this->dataMovie['directors'] = json_validate($resultDirectors) ? $resultDirectors : null;
        }
        $this->dataMovie['writers'] = null;
        if (!empty($dataInfo->writers)){
            $resultWriters = $this->startTranslate($dataInfo->writers);
            $resultWriters = str_replace(['«','»'] ,'"',$resultWriters);
            $resultWriters = str_replace("\u{200B}" ,'',$resultWriters);
            if (!strpos($resultWriters,'"')){
                $resultWriters = str_replace('[' ,'["',$resultWriters);
                $resultWriters = str_replace(']' ,'"]',$resultWriters);
                $resultWriters = str_replace(',' ,'","',$resultWriters);
                $resultWriters = trim($resultWriters);
            }
            $this->dataMovie['writers'] = json_validate($resultWriters) ? $resultWriters : null;
        }
        $this->dataMovie['story_line'] = null;
        if (!empty($dataInfo->story_line)){
            $resultStoryLine = $dataInfo->story_line;
            if (strlen($dataInfo->story_line) >= 4500){
                $resultStoryLine = mb_strimwidth($resultStoryLine,0,2500,"...");
            }
            $resultStoryLine = $this->startTranslate($resultStoryLine);
            $this->dataMovie['story_line'] = str_replace("\u{200B}" ,'',$resultStoryLine);
        }
        $this->dataMovie['countries'] = null;
        if (!empty($dataInfo->countries)){
            $resultCountries= $this->startTranslate($dataInfo->countries);
            $resultCountries = str_replace('».' ,'',$resultCountries);
            $resultCountries = str_replace(['«','»'] ,'"',$resultCountries);
            $resultCountries = str_replace("\u{200B}" ,'',$resultCountries);
            $this->dataMovie['countries'] = json_validate($resultCountries) ? $resultCountries : null;
        }
        $this->dataMovie['release_date'] = null;
        if (!empty($dataInfo->release_date)){
            if (str_contains($dataInfo->release_date,",")){
                $this->dataMovie['release_date'] = $this->startTranslate($dataInfo->release_date);
            }
        }
        $this->updateOrCreateLocalizing($this->dataMovie);
        $this->dataMovie = [];
    }
    public function translateCeleb($dataInfo, $celebId, $columnKey) {
        $this->columnId = $celebId;
        $this->columnKey = $columnKey;
        $this->dataCeleb['id_celeb'] = $dataInfo->id_celeb;
        $this->localizingTable = new CelebsInfoRu();
        if (!empty($dataInfo->filmography)){
            $filmographyDecode = json_decode($dataInfo->filmography, true);
            foreach ($filmographyDecode as $keySection => $valSection){
                if ($keySection == 'actor' || $keySection == 'actress' || $keySection == 'producer' || $keySection == 'writer' || $keySection == 'director' || $keySection == 'composer' || $keySection == 'soundtrack'){
                    foreach ($valSection as $keyId => $item) {
                        $contains_cyrillic_role = (bool) preg_match('/[\p{Cyrillic}]/u',$item['role']);
                        preg_match('/\d\d\d\d/', $item['year'], $contains_year);;
                        $contains_cyrillic_title = (bool) preg_match('/[\p{Cyrillic}]/u',$item['title']);
                        $resultFilmography[$keySection][$keyId]['role'] = $contains_cyrillic_role ? $item['role'] : $this->startTranslate($item['role']);
                        $resultFilmography[$keySection][$keyId]['year'] = $contains_year[0]??null;
                        $resultFilmography[$keySection][$keyId]['title'] = $contains_cyrillic_title ? $item['title'] : $this->startTranslate($item['title']);
                    }
                }
            }
            $this->dataCeleb['filmography'] = json_encode($resultFilmography,JSON_UNESCAPED_UNICODE);
        }
        if (!empty($dataInfo->nameActor)){
            $this->dataCeleb['nameActor'] = $this->startTranslate($dataInfo->nameActor);
        }
        if (!empty($dataInfo->birthdayLocation)){
            $this->dataCeleb['birthdayLocation'] = $this->startTranslate($dataInfo->birthdayLocation)??null;
        }
        if (!empty($dataInfo->dieLocation)){
            $this->dataCeleb['dieLocation'] = $this->startTranslate($dataInfo->dieLocation)??null;
        }
        $this->updateOrCreateLocalizing($this->dataCeleb);
        $this->dataCeleb = [];
    }
}
