<?php


namespace App\Http\Controllers;


use App\Models\LocalizingCelebsInfo;
use App\Models\LocalizingInfoMovies;
use \Dejurin\GoogleTranslateForFree;
use Illuminate\Support\Facades\Log;

class TranslatorController
{
    private $source = 'en';
    private $target = 'ru';
    private $attempts = 5;
    private $translator;
    private $loclizingTable = null;
    private $columnId = null;
    private $columnKey = null;

    private $dataMovie = [
        'id_movie' => null,
        'type_film' => null,
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
        try {
            $localizingModel = $this->loclizingTable::where($this->columnKey,$this->columnId)->first();
            $localizingModel ? $localizingModel::where($this->columnKey,$this->columnId)->update($data) : $this->loclizingTable::firstOrCreate($data);
        }
        catch (\Exception $e)
        {
            Log::info("ID-->>>{$this->columnId}");
            Log::info("ERROR--EXEPTION>>>{$e}");
        }
    }

    public function translateMovie($dataInfo, $movieId, $columnKey) {
        $this->columnId = $movieId;
        $this->columnKey = $columnKey;
        $this->dataMovie['id_movie'] = $dataInfo->id_movie;
        $this->dataMovie['type_film'] = $dataInfo->type_film;
        $this->loclizingTable = new LocalizingInfoMovies();
        $this->dataMovie['genres'] = null;
        if (!empty($dataInfo->genres)){
            $unpackGenres = unserialize($dataInfo->genres);
            $genres = array_unique($unpackGenres['genres']);
            $genres = json_encode($genres,JSON_UNESCAPED_UNICODE);
            $resultGenres = $this->startTranslate($genres);
            $resultGenres = str_replace(['«','»'] ,'"',$resultGenres);
            $this->dataMovie['genres'] = str_replace("\u{200B}" ,'',$resultGenres);
        }
        $this->dataMovie['cast'] = null;
        if (!empty($dataInfo->cast)){
            $resultCast = $this->startTranslate($dataInfo->cast);
            $explodeCast = explode(';',$resultCast);
            unset($explodeCast[0]);
            unset($resultCast);
            array_pop($explodeCast);
            $numericArr = [];
            foreach ($explodeCast as $expElem){
                if (preg_match('/"([^"]+)"/', $expElem, $matches)) {
                    array_push($numericArr,$matches[1]);
                } else {
                    //array_push($numericArr,null);
                }
            }
            foreach ($numericArr as $keyEl => $numElem){
                if (preg_match("/nm\d{1,10}/", $numElem, $matches)) {
                    $keyEl++;
                    if ($keyEl <= count($numericArr)){
                        foreach (array_slice($numericArr, $keyEl,2) as $keyGroup => $group) {
                            if ($keyGroup == 0){
                                $resultCast[trim($matches[0])]['actor'] = strpos($group, 'nm') !== 0 ? trim($group) : null;
                            }
                            if ($keyGroup == 1) {
                                $resultCast[trim($matches[0])]['role'] = strpos($group, 'nm') !== 0 ? trim($group) : null;
                            }
                        }
                    }
                }
            }
            $resultCast = json_encode($resultCast ?? null);
            $this->dataMovie['cast'] = json_validate($resultCast) ? $resultCast : null;
        }

        if (!empty($dataInfo->directors)){
            $unserializeDirectors = unserialize($dataInfo->directors);
            $directorsKeysArr = [];
            foreach ($unserializeDirectors['directors'] as $keyDirector => $director){
                $directorsKeysArr[$keyDirector] = trim(key($director));
            }
            $directorsKeysArr = array_unique($directorsKeysArr);
            $encodeDirectors = json_encode($directorsKeysArr,JSON_UNESCAPED_UNICODE);
            $resultDirectors = $this->startTranslate($encodeDirectors);
            $resultDirectors = str_replace(['«','»'] ,'"',$resultDirectors);
            $resultDirectors = str_replace(':""' ,"\":\"\"",$resultDirectors);
            $resultDirectors = str_replace("\u{200B}" ,'',$resultDirectors);
            $this->dataMovie['directors'] = json_validate($resultDirectors) ? $resultDirectors : null;
        }

        if (!empty($dataInfo->writers)){
            $unserializeWriters = unserialize($dataInfo->writers);
            $writerKeysArr = [];
            foreach ($unserializeWriters['writers'] as $keyWriter => $writer){
                $writerKeysArr[$keyWriter] = trim(key($writer));
            }
            $writerKeysArr = array_unique($writerKeysArr);
            $encodeWriters = json_encode($writerKeysArr,JSON_UNESCAPED_UNICODE);
            $resultWriters = $this->startTranslate($encodeWriters);
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

        if (!empty($dataInfo->story_line)){
            $resultStoryLine = $dataInfo->story_line;
            if (strlen($dataInfo->story_line) >= 4500){
                $resultStoryLine = mb_strimwidth($resultStoryLine,0,2500,"...");
            }
            $resultStoryLine = $this->startTranslate($resultStoryLine);
            $this->dataMovie['story_line'] = str_replace("\u{200B}" ,'',$resultStoryLine);
        }

        if (!empty($dataInfo->countries)){
            $unpackCountries = unserialize($dataInfo->countries);
            $countries = array_unique($unpackCountries['countries']);
            $countries = json_encode($countries,JSON_UNESCAPED_UNICODE);
            $resultCountries= $this->startTranslate($countries);
            $resultCountries = str_replace('».' ,'',$resultCountries);
            $resultCountries = str_replace(['«','»'] ,'"',$resultCountries);
            $resultCountries = str_replace("\u{200B}" ,'',$resultCountries);
            $this->dataMovie['countries'] = json_validate($resultCountries) ? $resultCountries : null;
        }

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
        $this->loclizingTable = new LocalizingCelebsInfo();
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
            $this->dataCeleb['birthdayLocation'] = $this->startTranslate($dataInfo->birthdayLocation);
        }
        if (!empty($dataInfo->dieLocation)){
            $this->dataCeleb['dieLocation'] = $this->startTranslate($dataInfo->dieLocation);
        }
        $this->updateOrCreateLocalizing($this->dataCeleb);
    }
}
