<?php


namespace App\Http\Controllers;


use App\Models\CelebsInfoRu;
use App\Models\MoviesInfoRu;
use App\Models\Tag;
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
            $this->localizingTable->query()->updateOrCreate([$this->columnKey=>$this->columnId],$data);
        } else {
            $this->localizingTable::create($data);
        }
    }
    public function translateSingle($input){
        return $this->startTranslate($input);
    }
    public function translateMovie($dataInfo, $movieId, $columnKey) {
        $this->columnId = $movieId;
        $this->columnKey = $columnKey;
        $this->dataMovie['id_movie'] = $dataInfo->id_movie;
        $this->localizingTable = new MoviesInfoRu();

        $this->dataMovie['genres'] = null;
        if (!empty($dataInfo->genres)) {
            $unpackGenres = json_decode($dataInfo->genres, true);
            if (is_string($unpackGenres)) {
                $unpackGenres = json_decode($unpackGenres, true);
            }
            if (!is_array($unpackGenres)) {
                Log::warning('Failed to parse genres array for movie id: ' . ($idFromUrl ?? 'unknown'));
            } else {
                $genresClean = array_unique($unpackGenres);
                $translatedGenresRu = [];
                foreach ($genresClean as $tagNameEn) {
                    $tagNameEn = trim($tagNameEn);
                    if (empty($tagNameEn)) continue;
                    $tagValue = strtolower(str_ireplace(' ', '_', $tagNameEn));
                    $tag = Tag::where('value', $tagValue)->first();
                    $tagRus = null;

                    if (!$tag) {
                        $tagRus = $this->startTranslate($tagNameEn);
                        $tagRus = $tagRus ? str_replace(['«','»'], '"', str_replace("\u{200B}", '', $tagRus)) : $tagNameEn;
                        $tagRus = ucfirst($tagRus);
                        Tag::create([
                            'value'       => $tagValue,
                            'tag_name_en' => $tagNameEn,
                            'tag_name_ru' => ucfirst($tagRus),
                        ]);
                        Log::info("New tag created: {$tagNameEn} -> {$tagRus}");

                    } else {
                        $tagRus = ucfirst($tag->tag_name_ru);
                        if (empty($tagRus)) {
                            $tagRus = $this->startTranslate($tagNameEn);
                            $tagRus = $tagRus ? str_replace(['«','»'], '"', str_replace("\u{200B}", '', $tagRus)) : $tagNameEn;

                            $tag->update([
                                'tag_name_ru' => $tagRus
                            ]);
                            Log::info("Tag updated with RU translation: {$tagNameEn} -> {$tagRus}");
                        }
                    }
                    $translatedGenresRu[] = $tagRus;
                }
                if (!empty($translatedGenresRu)) {
                    $resultGenres = json_encode($translatedGenresRu, JSON_UNESCAPED_UNICODE);
                    if (json_validate($resultGenres)) {
                        $this->dataMovie['genres'] = $resultGenres;
                    }
                }
            }
        }
        $this->dataMovie['cast'] = null;
        if (!empty($dataInfo->cast)){
            $castDecode = json_decode($dataInfo->cast, true);
            foreach ($castDecode as $id => $item) {
                if (!isset($item['actor']) || !isset($item['role'])) {
                    continue;
                }
                $resultCast[$id]['role'] = $this->startTranslate($item['role']);
                $resultCast[$id]['actor'] = $this->startTranslate($item['actor']);
            }
//            foreach ($castDecode as $id => $item) {
//                $resultCast[$id]['role'] = $this->startTranslate($item['role'] ?? null);
//                $resultCast[$id]['actor'] = $this->startTranslate($item['actor'] ?? null);
//            }
            $resultCast = json_encode($resultCast ,JSON_UNESCAPED_UNICODE);
            $this->dataMovie['cast'] = json_validate($resultCast) ? $resultCast : null;
        }

        $this->dataMovie['directors'] = null;
        if (!empty($dataInfo->directors)){

            $resultDirectors = $this->startTranslate($dataInfo->directors);
            $resultDirectors = str_replace(['«','»'] ,'"',$resultDirectors);
            $resultDirectors = str_replace(':""' ,"\":\"\"",$resultDirectors);
            $resultDirectors = str_replace("\u{200B}" ,'',$resultDirectors);

            $this->dataMovie['directors'] = json_validate($resultDirectors) ? $resultDirectors : $dataInfo->directors;

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
            $this->dataMovie['writers'] = json_validate($resultWriters) ? $resultWriters : $dataInfo->writers;
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
                        $resultFilmography[$keySection][$keyId]['title'] = $contains_cyrillic_title ? ucfirst($item['title']) : ucfirst($this->startTranslate($item['title']));
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
