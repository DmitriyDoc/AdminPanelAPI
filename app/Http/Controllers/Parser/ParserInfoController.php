<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\MoviesInfoTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParserInfoController extends ParserController
{
    //const SELECT_TABLE = 'movies_id_type_video';
    //const SELECT_TABLE = 'movies_id_type_tv_special';
    //const SELECT_TABLE = 'movies_id_type_tv_series';
    //const SELECT_TABLE = 'movies_id_type_tv_movie';
    //const SELECT_TABLE = 'movies_id_type_mini_series';
    //const SELECT_TABLE = 'movies_id_type_feature_film';
    //const SELECT_TABLE = 'movies_id_type_tv_short';
    //const SELECT_TABLE = 'movies_id_type_short_film';

    //const UPDATE_INFO_TABLE = 'movies_info_video';
    //const UPDATE_INFO_TABLE = 'movies_info_tv_special';
    //const UPDATE_INFO_TABLE = 'movies_info_tv_series';
    //const UPDATE_INFO_TABLE = 'movies_info_tv_movie';
    //const UPDATE_INFO_TABLE = 'movies_info_mini_series';
    //const UPDATE_INFO_TABLE = 'movies_info_feature_film';
    //const UPDATE_INFO_TABLE = 'movies_info_tv_short';
    //const UPDATE_INFO_TABLE = 'movies_info_short_film';

    private $links = [];

    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        return;
        $this->signByField = 'id_movie';
        $this->dateFrom = '2023-05-27';
        $this->select_id_table = 'movies_id_type_feature_film';
        $this->update_info_table = 'movies_info_feature_film';

        DB::table($this->select_id_table)->where('created_at','>=', $this->dateFrom)->orderBy('id')->chunk(30, function ($ids) {
            foreach ($ids as $id) {
                array_push($this->links,$this->domen.'/title/'.$id->id_movie);
            }
            $pages = (new CurlConnectorController())->getCurlMulty($this->links);
            $this->links = [];
            if (is_array($pages)){
                $this->getMoviesInfo($pages);
                //usleep(30000);
            }
        });
    }
}
