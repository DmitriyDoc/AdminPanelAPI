<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Parser\CurlConnectorController;
use App\Http\Controllers\Parser\ParserIdTypeController;
use App\Http\Controllers\Parser\ParserStartController;
use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use App\Traits\Components\CelebsCreditsTrait;
use App\Traits\Components\CelebsInfoTrait;
use App\Traits\Components\IdByTypeTrait;
use App\Traits\Components\IdImagesTrait;
use App\Traits\Components\ImagesTrait;
use App\Traits\Components\MoviesInfoTrait;
use App\Traits\ParserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;


class ParserController extends Controller
{
    const ID_PATTERN = '/tt\d{1,10}/';
    const TITLE_PATTERN = "/^[^\s]*\s[^\s]*/";
    const YEAR_PATTERN = "/\d\d\d\d/";
    const BUDGET_PATTERN = "/[^(*]+/";
    const ACTOR_PATTERN = "/nm\d{1,10}/";
    const ID_IMG_PATTERN = '/rm\d{1,10}/';
    const CLEAR_DIGITS_PATTERN = '/^\d*\.\s/';

    protected $update_id_images_table;
    protected $update_id_posters_table;
    protected $update_info_table;
    protected $update_en_info_table;
    protected $update_images_table;
    protected $update_posters_table;
    protected $select_id_table;
    protected $insert_id_table;

    protected $occupations = [
        0 => 'actor',
        1 => 'actress',
        2 => 'producer',
        3 => 'writer',
        5 => 'soundtrack',
        6 => 'director',
        7 => 'composer',
//        8 => 'production_manager',
//        9 => 'miscellaneous',
//        10 => 'self',
//        11 => 'editor',
//        12 => 'cinematographer',
//        13 => 'costume_designer',
//        14 => 'production_designer',
//        15 => 'art_director',
//        16 => 'music_department',
//        17 => 'visual_effects',
//        18 => 'assistant_director',
//        19 => 'camera_department',
//        20 => 'archive_footage',
//        21 => 'thanks',
//        22 => 'casting_director',
//        23 => 'casting_department',
//        24 => 'make_up_department',
//        25 => 'art_department',
//        26 => 'set_decorator',
//        27 => 'transportation_department',
//        28 => 'special_effects',
//        29 => 'animation_department',
//        30 => 'sound_department',
    ];

    public $allowedTableNames = [
        'FeatureFilm' => [
            'segment' => 'feature_film',
            'type' => 'feature',
        ],
        'MiniSeries' => [
            'segment' => 'mini_series',
            'type' => 'tv_miniseries',
        ],
        'ShortFilm' => [
            'segment' => 'short_film',
            'type' => 'short',
        ],
        'TvMovie' => [
            'segment' => 'tv_movie',
            'type' => 'tv_movie',
        ],
        'TvSeries' => [
            'segment' => 'tv_series',
            'type' => 'tv_series',
        ],
        'TvShort' => [
            'segment' => 'tv_short',
            'type' => 'tv_short',
        ],
        'TvSpecial' => [
            'segment' => 'tv_special',
            'type' => 'tv_special',
        ],
        'Video' => [
            'segment' => 'video',
            'type' => 'video',
        ],
    ];

    protected $domen = 'https://www.imdb.com';
    protected $signByField;
    protected $typeFilm;
    protected $imgUrlFragment;
    protected $chunkSize;
    protected $localizing;

    protected $start = 0;
    protected $urls = [];
    protected $dateFrom;
    protected $dateTo;
    protected $flagType;//movies=true;celebs=false;
    protected $flagNewUpdate;//new=true; and update=false;
    protected $titleType;
    protected $refine;

    protected $sort;
    protected $typeImages;
    protected $typePosters;

    protected $idMovies = [];
    protected $idCeleb = [];
    protected $linksInfo = [];
    protected $linksIdsImages = [];
    protected $linksIdsPosters = [];
    protected $linksImages = [];
    protected $linksPosters = [];
    protected $linksCredits = [];

    use ParserTrait,IdByTypeTrait,MoviesInfoTrait,IdImagesTrait,ImagesTrait,CelebsInfoTrait,CelebsCreditsTrait;

    public function __construct($params = [])
    {
        if (!empty($params)){
            $this->flagType = $params['flag'];
            $this->dateFrom = $params['date_from'];
            $this->dateTo = $params['date_till'];
            $this->sort = $params['sort'];
            $this->typeImages = $params['type_images'];
            $this->typePosters = $params['type_posters'];
        }
        $this->localizing = new TranslatorController();
    }

    public function __call( $name, $arguments ) {
        call_user_func($name, $arguments);
    }
    protected function createIdArrayAndGetImages( $imagesTable, $linksArray) {
        $picturesIds =  Redis::get('pictures_ids_data');
        $picturesIdsDecode = json_decode($picturesIds,true);
        if (!empty($picturesIdsDecode)) {
            foreach ($picturesIdsDecode as $idMovie =>$imagesIdsArray) {
                foreach ($imagesIdsArray as $idImage) {
                    $linksArray[$idMovie][] = $this->domen . $this->imgUrlFragment . $idMovie . '/mediaviewer/' . $idImage;
                }
            }
            Redis::del('pictures_ids_data');
        }
        if (!empty($linksArray)){
            $this->getImages( $linksArray, $imagesTable );
        }
    }
    protected function linksGetter( $links, $methodName,  $pattern = null ){
        if (!empty($links)){
            $infoChunks = array_chunk($links, $this->chunkSize);
            $connector = new CurlConnectorController();
            foreach ($infoChunks as $chunk) {
                $pages = $connector->getCurlMulty($chunk);
                if (is_array($pages)){
                    $this->{$methodName}( $pages, $pattern );
                }
            }
        }
    }

    public function parseMoviesByPeriod($allowMovieTypes) : void
    {
        $period = new \DatePeriod(
            new \DateTime($this->dateFrom),
            new \DateInterval('P1D'),
            new \DateTime($this->dateTo . '23:59')
        );
        foreach ($allowMovieTypes as $type){
            if (array_key_exists($type, $this->allowedTableNames)) {
                $arg['segment'] = $this->allowedTableNames[$type]['segment'];
                $arg['typeImages'] = $this->typeImages;
                $arg['typePosters'] = $this->typePosters;
                foreach ($period as $key => $day) {
                    $this->titleType = $this->allowedTableNames[$type]['type'];
                    array_push($this->urls,"{$this->domen}/search/title/?title_type={$this->titleType}&release_date={$day->format('Y-m-d')},{$day->format('Y-m-d')}&sort={$this->sort},asc");
                    array_push($this->urls,"{$this->domen}/search/title/?title_type={$this->titleType}&release_date={$day->format('Y-m-d')},{$day->format('Y-m-d')}&sort={$this->sort},desc");
                    $this->getIdByType($arg);
                    session()->push('tracking.report.finishIdsPeriod', $day->format("Y-m-d") . " for movie type: " . $this->allowedTableNames[$type]['type']);
                    session()->save();
                    Log::info(">>> PARSE PERIOD : {$day->format("Y-m-d")} IDS FINISH FOR ->>>", [ $this->allowedTableNames[$type]['type'] ]);
                }
                session()->push('tracking.report.finishInfo',$this->allowedTableNames[$type]['type']);
                session()->save();
                Log::info(">>>  PARSE INFO FINISH FOR ->>>", [ $this->allowedTableNames[$type]['type'] ]);
            }
        }
    }

    public function parsePersons($personsSource,$switchNewUpdate) : void
    {
        foreach ($personsSource as $group) {
            $this->insert_id_table = 'celebs_id';
            $this->titleType = $group;
            $this->flagNewUpdate = $switchNewUpdate;
            array_push($this->urls,"{$this->domen}/search/name/{$this->titleType}&sort={$this->sort},asc");
            array_push($this->urls,"{$this->domen}/search/name/{$this->titleType}&sort={$this->sort},desc");
            $this->getIdByType();
            session()->push('tracking.report.finishInfo',$group);
            session()->save();
            Log::info('>>> PARSE CELEBS ID BY:', [$this->titleType]);
        }
        Log::info('>>> PARSED CELEBS FINISH');
    }
//    public function actualizeYearTitleForTableIdType($allowMovieTypes = [])
//    {
//        if (!empty($allowMovieTypes)){
//            foreach ($allowMovieTypes as $table){
//                $modelInfo = convertVariableToModelName('Info', $table, ['App', 'Models']);
//                $modelIdType = convertVariableToModelName('IdType', $table, ['App', 'Models']);
//                $modelInfo = $modelInfo::select('id_movie','title','year_release')->limit(50)->orderBy('created_at','desc')->get();
//                foreach ($modelInfo as $key => $item){
//                    if (!empty($item['year_release'])){
//                        $modelIdType::where('id_movie',$item['id_movie'])->update([
//                            'title' => $item['title'],
//                            'year' => $item['year_release']
//                        ]);
//                    }
//                }
//                session()->push('tracking.report.finishActualize', $table);
//                session()->save();
//                Log::info(">>> ACTUALIZE ID TYPE FINISH",[$table]);
//            }
//        }
//    }
}
