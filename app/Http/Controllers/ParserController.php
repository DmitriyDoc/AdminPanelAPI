<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Parser\CurlConnectorController;
use App\Traits\Components\CelebsCreditsTrait;
use App\Traits\Components\CelebsInfoTrait;
use App\Traits\Components\IdImagesTrait;
use App\Traits\Components\ImagesTrait;
use App\Traits\Components\MoviesInfoTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


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
        7 => 'editor',
        8 => 'production_manager',
        9 => 'miscellaneous',
        10 => 'self',
        11 => 'editor',
        12 => 'cinematographer',
        13 => 'costume_designer',
        14 => 'production_designer',
        15 => 'art_director',
        16 => 'music_department',
        17 => 'visual_effects',
        18 => 'assistant_director',
        19 => 'camera_department',
        20 => 'archive_footage',
        21 => 'thanks',
        22 => 'casting_director',
        23 => 'casting_department',
        24 => 'make_up_department',
        25 => 'art_department',
        26 => 'set_decorator',
        27 => 'transportation_department',
        28 => 'special_effects',
        29 => 'animation_department',
        30 => 'sound_department',
        31 => 'composer',
    ];
    protected $domen = 'https://www.imdb.com';
    protected $signByField;
    protected $imgUrlFragment;
    protected $chunkSize;

    protected $start = 0;
    protected $dateFrom;
    protected $dateTo;
    protected $flagType;//movies=true;celebs=false;
    protected $titleType;
    protected $refine;

    protected $idMovies = [];
    protected $idCeleb = [];
    protected $linksInfo = [];
    protected $linksIdsImages = [];
    protected $linksIdsPosters = [];
    protected $linksImages = [];
    protected $linksPosters = [];
    protected $linksCredits = [];

    use ParserTrait,MoviesInfoTrait,IdImagesTrait,ImagesTrait,CelebsInfoTrait,CelebsCreditsTrait;

    public function __call( $name, $arguments ) {
        call_user_func($name, $arguments);
    }
    protected function createIdArrayAndGetImages( $imagesIdTable, $imagesTable, $linksArray, $dataLinks ) {
        if ($imgIds = DB::table($imagesIdTable)->whereIn($this->signByField,$dataLinks)->orderBy('id')->get()){
            if ($imgIds->isNotEmpty()){
                foreach ($imgIds as $id) {
                    if ($decodeArrIdImages = json_decode($id->id_images)) {
                        if (is_array($decodeArrIdImages)) {
                            foreach ($decodeArrIdImages as $idImage) {
                                if (!empty($idImage)) {
                                    $linksArray[$id->{$this->signByField}][] = $this->domen.$this->imgUrlFragment.$id->{$this->signByField}.'/mediaviewer/'.$idImage;
                                }
                            }
                        }
                    }
                }
            }

        }
        if (!empty($linksArray)){
            $this->getImages( $linksArray, $imagesTable );
        }

    }
    protected function linksGetter( $links, $methodName, $table = null,$pattern = null ){
        if (!empty($links)){
            $infoChunks = array_chunk($links, $this->chunkSize);
            foreach ($infoChunks as $chunk) {
                $pages = (new CurlConnectorController())->getCurlMulty($chunk);
                if (is_array($pages)){
                    $this->{$methodName}( $pages, $table, $pattern );
                }
            }
        }
    }
}
