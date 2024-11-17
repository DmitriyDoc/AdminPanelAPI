<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Parser\ParserUpdateMovieController;
use App\Models\IdTypeMiniSeries;
use App\Models\IdTypeMovies;
use App\Models\InfoCelebs;
use App\Models\InfoFeatureFilm;
use App\Models\InfoMiniSeries;
use App\Models\InfoShortFilm;
use App\Models\InfoTvMovie;
use App\Models\InfoTvSeries;
use App\Models\InfoTvShort;
use App\Models\InfoTvSpecial;
use App\Models\InfoVideo;
use App\Models\LocalizingCelebsInfo;
use App\Models\LocalizingFranchise;
use App\Models\LocalizingInfoMovies;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use \Dejurin\GoogleTranslateForFree;
use Illuminate\Http\Request;
use function Ramsey\Uuid\Lazy\toString;

class DashboardController
{
    public function index()
    {
        $data = [];
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
            8=>'Celebs',
        ];
        session()->forget('tracking.dashboardPercentageBar');
        session(['tracking.dashboardPercentageBar' => 0]);
        session()->save();

        foreach ($allowedTableNames as $index => $name) {
            $model = convertVariableToModelName('IdType',$name, ['App', 'Models']);
            $data[$index]['key'] = $index.'_'.$name;
            $data[$index]['title'] = $name;
            $data[$index]['count'] = $model->getCountAttribute();
            $data[$index]['lastAddCount'] = $model->getLastDayCountAttribute();
            session()->increment('tracking.dashboardPercentageBar',1);
            session()->save();
        }
        return [
            'success' => true,
            'status' => 200,
            'data' => $data
        ];
    }

    public function testCelebs()
    {
        $source = 'en';
        $target = 'ru';
        $attempts = 5;
        $tr = new GoogleTranslateForFree();

        $model = InfoCelebs::query()->offset(1910000)->limit(10000)->get(['nameActor','id_celeb','filmography','birthdayLocation','dieLocation']);
        foreach ($model->toArray() as $field){
            $resultFilmography = null;
            $resultNameActor = null;
            $resultBirthdayLocation = null;
            $resultDieLocation = null;
            if (!empty($field['filmography'])){
                $filmographyDecode = json_decode($field['filmography'], true);
                foreach ($filmographyDecode as $keySection => $valSection){
                    if ($keySection == 'actor' || $keySection == 'actress' || $keySection == 'producer' || $keySection == 'writer' || $keySection == 'director' || $keySection == 'composer' || $keySection == 'soundtrack'){
                        foreach ($valSection as $keyId => $item) {
                            $contains_cyrillic_role = (bool) preg_match('/[\p{Cyrillic}]/u',$item['role']);
                            preg_match('/\d\d\d\d/', $item['year'], $contains_year);;
                            $contains_cyrillic_title = (bool) preg_match('/[\p{Cyrillic}]/u',$item['title']);
                            $resultFilmography[$keySection][$keyId]['role'] = $contains_cyrillic_role ? $item['role'] : $tr->translate($source, $target, $item['role']??null, $attempts)??null;
                            $resultFilmography[$keySection][$keyId]['year'] = $contains_year[0]??null;
                            $resultFilmography[$keySection][$keyId]['title'] = $contains_cyrillic_title ? $item['title'] : $tr->translate($source, $target, $item['title']??null, $attempts)??null;
                        }

                    }
                }
                $resultFilmography = json_encode($resultFilmography,JSON_UNESCAPED_UNICODE);
            }
            if (!empty($field['nameActor'])){
                $resultNameActor = $tr->translate($source, $target, $field['nameActor'], $attempts);
            }
            if (!empty($field['birthdayLocation'])){
                $resultBirthdayLocation = $tr->translate($source, $target, $field['birthdayLocation'], $attempts);
            }
            if (!empty($field['dieLocation'])){
                $resultDieLocation = $tr->translate($source, $target, $field['dieLocation'], $attempts);
            }
            try
            {
                $model = LocalizingCelebsInfo::where('id_celeb',$field['id_celeb'])->first();
                if ($model) {
                    $model::where('id_celeb',$field['id_celeb'])->update([
                        'id_celeb'=>$field['id_celeb'],
                        'filmography'=>$resultFilmography,
                        'nameActor'=>$resultNameActor,
                        'birthdayLocation'=>$resultBirthdayLocation,
                        'dieLocation'=>$resultDieLocation,
                    ]);
                } else {
                    LocalizingCelebsInfo::firstOrCreate([
                        'id_celeb'=>$field['id_celeb'],
                        'filmography'=>$resultFilmography,
                        'nameActor'=>$resultNameActor,
                        'birthdayLocation'=>$resultBirthdayLocation,
                        'dieLocation'=>$resultDieLocation,
                    ]);
                }
            }
            catch (\Exception $e)
            {
                Log::info("ID--CELEB>>>{$field['id_celeb']}");
                Log::info("ERROR-CELEB-EXEPTION>>>{$e}");
            }
        }
    }
    public function test()
    {
//        $source = 'en';
//        $target = 'ru';
//        $attempts = 5;
//
//        $tr = new GoogleTranslateForFree();
//        //$test = $tr->translate($source, $target, "Mr. Leigh", $attempts);
//        //dd($test);
//        //$model = InfoFeatureFilm::query()->limit(10000)->get(['story_line','id_movie','type_film']);
//        //$model = InfoFeatureFilm::whereNotNull('release_date')->count();
//        //$model = InfoShortFilm::query()->where('id_movie','=','tt11332768')->get(['genres','cast','directors','writers','story_line','countries','release_date','id_movie','type_film']);
//        $model = LocalizingFranchise::all();
//        foreach ($model->toArray() as $item){
//            $resultLabel= null;
//            $resultLabel = $tr->translate($source, $target,$item['label'], $attempts);
////            $resultCast = null;
////            $resultDirectors = null;
////            $resultWriters = null;
////            $resultStoryLine = null;
////            $resultCountries = null;
////            $resultReleaseDate = null;
////            if (!empty($item['genres'])){
////                $unpackGenres = unserialize($item['genres']);
////                $genres = array_unique($unpackGenres['genres']);
////                $genres = json_encode($genres,JSON_UNESCAPED_UNICODE);
////                $resultGenres = $tr->translate($source, $target, $genres, $attempts);
////                $resultGenres = str_replace(['«','»'] ,'"',$resultGenres);
////                $resultGenres = str_replace("\u{200B}" ,'',$resultGenres);
////            }
////            if (!empty($item['cast'])){
////                $resultCast = $tr->translate($source, $target,$item['cast'], $attempts);
////                $explodeCast = explode(';',$resultCast);
////
////                unset($explodeCast[0]);
////                unset($resultCast);
////                array_pop($explodeCast);
////                $numericArr = [];
////                foreach ($explodeCast as $expElem){
////                    if (preg_match('/"([^"]+)"/', $expElem, $matches)) {
////                        array_push($numericArr,$matches[1]);
////                    } else {
////                        //array_push($numericArr,null);
////                    }
////                }
////                foreach ($numericArr as $keyEl => $numElem){
////                    if (preg_match("/nm\d{1,10}/", $numElem, $matches)) {
////                        $keyEl++;
////                        if ($keyEl <= count($numericArr)){
////                            foreach (array_slice($numericArr, $keyEl,2) as $keyGroup => $group) {
////                                if ($keyGroup == 0){
////                                    $resultCast[trim($matches[0])]['actor'] = strpos($group, 'nm') !== 0 ? trim($group) : null;
////                                }
////                                if ($keyGroup == 1) {
////                                    $resultCast[trim($matches[0])]['role'] = strpos($group, 'nm') !== 0 ? trim($group) : null;
////                                }
////                            }
////                        }
////                    }
////                }
////                $resultCast = json_encode($resultCast ?? null);
////                $resultCast = json_validate($resultCast) ? $resultCast : null;
////                //dd($resultCast);
////            }
////
////            if (!empty($item['directors'])){
////                $unserializeDirectors = unserialize($item['directors']);
////                $directorsKeysArr = [];
////                foreach ($unserializeDirectors['directors'] as $keyDirector => $director){
////                    $directorsKeysArr[$keyDirector] = trim(key($director));
////                }
////                $directorsKeysArr = array_unique($directorsKeysArr);
////                $encodeDirectors = json_encode($directorsKeysArr,JSON_UNESCAPED_UNICODE);
////                $resultDirectors = $tr->translate($source, $target,$encodeDirectors, $attempts);
////                $resultDirectors = str_replace(['«','»'] ,'"',$resultDirectors);
////                $resultDirectors = str_replace(':""' ,"\":\"\"",$resultDirectors);
////                $resultDirectors = str_replace("\u{200B}" ,'',$resultDirectors);
////                $resultDirectors = json_validate($resultDirectors) ? $resultDirectors : null;
////                //dd($resultDirectors);
////            }
////            if (!empty($item['writers'])){
////                $unserializeWriters = unserialize($item['writers']);
////                $writerKeysArr = [];
////                foreach ($unserializeWriters['writers'] as $keyWriter => $writer){
////                    $writerKeysArr[$keyWriter] = trim(key($writer));
////                }
////                $writerKeysArr = array_unique($writerKeysArr);
////                $encodeWriters = json_encode($writerKeysArr,JSON_UNESCAPED_UNICODE);
////                $resultWriters = $tr->translate($source, $target,$encodeWriters, $attempts);
////                $resultWriters = str_replace(['«','»'] ,'"',$resultWriters);
////                $resultWriters = str_replace("\u{200B}" ,'',$resultWriters);
////                if (!strpos($resultWriters,'"')){
////                    $resultWriters = str_replace('[' ,'["',$resultWriters);
////                    $resultWriters = str_replace(']' ,'"]',$resultWriters);
////                    $resultWriters = str_replace(',' ,'","',$resultWriters);
////                    $resultWriters = trim($resultWriters);
////                }
////                $resultWriters = json_validate($resultWriters) ? $resultWriters : null;
////                //dd($resultWriters);
////            }
////            if (!empty($item['story_line'])){
////
////                $resultStoryLine = $item['story_line'];
////                if (strlen($item['story_line']) >= 4500){
////                    $resultStoryLine = mb_strimwidth($resultStoryLine,0,2500,"...");
////                }
////                $resultStoryLine = $tr->translate($source, $target, $resultStoryLine, $attempts);
////                $resultStoryLine = str_replace("\u{200B}" ,'',$resultStoryLine);
////            }
////            if (!empty($item['countries'])){
////                $unpackCountries = unserialize($item['countries']);
////                $countries = array_unique($unpackCountries['countries']);
////                $countries = json_encode($countries,JSON_UNESCAPED_UNICODE);
////                $resultCountries= $tr->translate($source, $target, $countries, $attempts);
////                $resultCountries = str_replace('».' ,'',$resultCountries);
////                $resultCountries = str_replace(['«','»'] ,'"',$resultCountries);
////                $resultCountries = str_replace("\u{200B}" ,'',$resultCountries);
////                $resultCountries = json_validate($resultCountries) ? $resultCountries : null;
////                //dd($resultCountries);
////
////            }
////            if (!empty($item['release_date'])){
////                if (str_contains($item['release_date'],",")){
////                    $resultReleaseDate = $tr->translate($source, $target, $item['release_date'], $attempts);
////                }
////            }
//            try
//            {
//                LocalizingFranchise::where('label',$item['label'])->update([
//                    'label_ru'=>$resultLabel,
//                ]);
//
//            }
//            catch (\Exception $e)
//            {
//                Log::info("Label--MOVIE>>>{$item['label']}");
//                Log::info("ERROR--EXEPTION>>>{$e}");
//            }
//
//        }
    }
}
