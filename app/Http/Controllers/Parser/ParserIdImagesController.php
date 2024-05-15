<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\IdImagesTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParserIdImagesController extends ParserController
{

    //const SELECT_TABLE = 'movies_id_type_video';
    //const SELECT_TABLE = 'movies_id_type_tv_special';
    //const SELECT_TABLE = 'movies_id_type_tv_series';
    //const SELECT_TABLE = 'movies_id_type_tv_movie';
    //const SELECT_TABLE = 'movies_id_type_mini_series';
    //const SELECT_TABLE = 'movies_id_type_feature_film';
    //const SELECT_TABLE = 'movies_id_type_tv_short';
    //const SELECT_TABLE = 'movies_id_type_short_film';
    //const SELECT_TABLE = 'celebs_id';


    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_video';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_tv_special';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_tv_series';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_tv_movie';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_mini_series';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_feature_film';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_tv_short';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_images_short_film';

    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_short_film';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_video';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_tv_special';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_tv_series';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_tv_movie';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_mini_series';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_feature_film';
    //const UPDATE_ID_IMAGES_TABLE = 'movies_id_posters_tv_short';
    //const UPDATE_ID_IMAGES_TABLE = 'celebs_id_images';


    private $links = [];


    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        return;
        $this->signByField = 'id_celeb'; //id_movie
        $this->dateFrom = '2023-05-27';
        $this->flagType = false;
        $this->refine = 'still_frame';//poster
        $this->select_id_table = 'movies_id_type_feature_film';
        $this->update_images_table = 'movies_id_images_feature_film';

        DB::table($this->select_id_table)->where('created_at','>=', $this->dateFrom)->orderBy('id')->chunk(1, function ($ids) {
            if ($this->flagType){
                foreach ($ids as $id) {
                    array_push($this->links,$this->domen.'/title/'.$id->movie_id.'/mediaindex/?contentTypes='.$this->refine);
                }
            } else {
                foreach ($ids as $id) {
                    array_push($this->links,$this->domen.'/name/'.$id->actor_id.'/mediaindex/?contentTypes==publicity');
                }
            }
            $pages = (new CurlConnectorController())->getCurlMulty($this->links);
            $this->links = [];
            if (is_array($pages)){
                $this->getIdImages($pages,$this->update_id_images_table,self::ID_PATTERN);
            }
        });
    }

}
