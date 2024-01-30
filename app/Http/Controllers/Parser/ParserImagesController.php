<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\ImagesTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParserImagesController extends ParserController
{

    //const SELECT_TABLE = 'movies_id_images_video';
    //const SELECT_TABLE = 'movies_id_images_tv_special';
    //const SELECT_TABLE = 'movies_id_images_tv_series';
    //const SELECT_TABLE = 'movies_id_images_tv_movie';
    //const SELECT_TABLE = 'movies_id_images_mini_series';
    //const SELECT_TABLE = 'movies_id_images_feature_film';
    //const SELECT_TABLE = 'movies_id_images_tv_short';
    //const SELECT_TABLE = 'movies_id_images_short_film';

    //const SELECT_TABLE = 'movies_id_posters_feature_film';
    //const SELECT_TABLE = 'movies_id_posters_short_film';
    //const SELECT_TABLE = 'movies_id_posters_video';
    //const SELECT_TABLE = 'movies_id_posters_tv_special';
    //const SELECT_TABLE = 'movies_id_posters_tv_series';
    //const SELECT_TABLE = 'movies_id_posters_tv_movie';
    //const SELECT_TABLE = 'movies_id_posters_mini_series';
    //const SELECT_TABLE = 'movies_id_posters_tv_short';
    //const SELECT_TABLE = 'celebs_id_images';

    //const UPDATE_IMAGES_TABLE = 'movies_images_video';
    //const UPDATE_IMAGES_TABLE = 'movies_images_tv_special';
    //const UPDATE_IMAGES_TABLE = 'movies_images_tv_series';
    //const UPDATE_IMAGES_TABLE = 'movies_images_tv_movie';
    //const UPDATE_IMAGES_TABLE = 'movies_images_mini_series';
    //const UPDATE_IMAGES_TABLE = 'movies_images_feature_film';
    //const UPDATE_IMAGES_TABLE = 'movies_images_tv_short';
    //const UPDATE_IMAGES_TABLE = 'movies_images_short_film';

    //const UPDATE_IMAGES_TABLE = 'movies_posters_feature_film';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_short_film';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_video';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_tv_special';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_tv_series';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_tv_movie';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_mini_series';
    //const UPDATE_IMAGES_TABLE = 'movies_posters_tv_short';
    //const UPDATE_IMAGES_TABLE = 'celebs_images';


    private $links = [];

    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        return;
        $this->signByField = 'id_movie'; //id_celeb
        $this->dateFrom = '2023-05-27';
        $this->flagType = false;
        $this->select_id_table = 'celebs_id_images';
        $this->update_images_table = 'celebs_images';

        DB::table($this->select_id_table)->where('created_at','>=', $this->dateFrom)->orderBy('id')->chunk(30, function ($ids) {
            foreach ($ids as $id) {
                if ($decodeArrIdImages = json_decode($id->id_images)) {
                    if (is_array($decodeArrIdImages)) {
                        foreach ($decodeArrIdImages as $idImage) {
                            if ($this->flagType){
                                if (!empty($idImage)) {
                                    $this->links[$id->id_movie][] = $this->domen.'/title/'.$id->id_movie.'/mediaviewer/'.$idImage;
                                }
                            } else {
                                if (!empty($idImage)) {
                                    $this->links[$id->id_celeb][] = $this->domen.'/name/'.$id->id_celeb.'/mediaviewer/'.$idImage;
                                }
                            }
                        }
                    }
                }
            }
            if (!empty($this->links)){
                $this->getImages($this->links,$this->update_images_table);
            }
            $this->links = [];
        });
    }

}
