<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ParserUpdateMovieController extends ParserController
{

    /**
     * Handle the incoming request.
     */
    public function index($filmType, $tableDateFrom): void
    {
        $this->signByField = 'id_movie';
        $this->imgUrlFragment = '/title/';
        $this->chunkSize = 10;
        $segment = $filmType;
        $this->dateFrom = $tableDateFrom;
        $this->insert_id_table = 'movies_id_type_' . $segment;
        $this->update_info_table = 'movies_info_' . $segment;
        $this->update_id_images_table = 'movies_id_images_' . $segment;
        $this->update_id_posters_table = 'movies_id_posters_' . $segment;
        $this->update_images_table = 'movies_images_' . $segment;
        $this->update_posters_table = 'movies_posters_' . $segment;

        if (empty($this->idMovies)) {
            DB::table($this->insert_id_table)->where('created_at', '>=', $this->dateFrom)->orderBy('id')->chunk(30, function ($ids) {
                foreach ($ids as $id) {
                    array_push($this->idMovies, $id->{$this->signByField});
                }
            });
        }

        if (!empty($this->idMovies)) {
            foreach ($this->idMovies as $id) {
                array_push($this->linksInfo, $this->domen . $this->imgUrlFragment . $id);
                array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes=still_frame');
                array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes=poster');
            }

            $this->linksGetter($this->linksInfo, 'getMoviesInfo');
            $this->linksGetter($this->linksIdsImages, 'getIdImages', $this->update_id_images_table, self::ID_PATTERN, $this->signByField);
            $this->linksGetter($this->linksIdsPosters, 'getIdImages', $this->update_id_posters_table, self::ID_PATTERN, $this->signByField);

            $this->createIdArrayAndGetImages($this->update_id_images_table, $this->update_images_table, $this->linksImages, $this->idMovies);
            $this->createIdArrayAndGetImages($this->update_id_posters_table, $this->update_posters_table, $this->linksPosters, $this->idMovies);

            foreach ($this->idMovies as $id) {
                $this->localizing($id);
            }
        }
    }

    public function update(Request $request)
    {
        if ($data = $request->all()){

            $model = convertVariableToModelName('IdType', $data['data']['type'], ['App', 'Models']);
            if ($segment = $model->segment ) {
                $this->signByField = 'id_movie';
                $this->imgUrlFragment = '/title/';
                $this->chunkSize = 1;

                $this->update_info_table = 'movies_info_' . $segment;
                $this->update_id_images_table = 'movies_id_images_' . $segment;
                $this->update_id_posters_table = 'movies_id_posters_' . $segment;
                $this->update_images_table = 'movies_images_' . $segment;
                $this->update_posters_table = 'movies_posters_' . $segment;

                if (empty($this->idMovies)) {
                    array_push($this->idMovies,  $data['data']['id']);
                }

                if (!empty($this->idMovies)) {
                    foreach ($this->idMovies as $id) {
                        array_push($this->linksInfo, $this->domen . $this->imgUrlFragment . $id);
                        array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes=still_frame');
                        array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes=poster');

                    }

                    $this->linksGetter($this->linksInfo, 'getMoviesInfo');
                    $this->linksGetter($this->linksIdsImages, 'getIdImages', $this->update_id_images_table, self::ID_PATTERN);
                    $this->linksGetter($this->linksIdsPosters, 'getIdImages', $this->update_id_posters_table, self::ID_PATTERN);
                    $this->createIdArrayAndGetImages($this->update_id_posters_table, $this->update_posters_table, $this->linksPosters, $this->idMovies);
                    $this->createIdArrayAndGetImages($this->update_id_images_table, $this->update_images_table, $this->linksImages, $this->idMovies);

                    $this->localizing($id);

                    $this->touchDB($model, $data['data']['id'],$this->signByField);
                    $this->idMovies = [];
                }
            }
        }

    }
    public function localizing($movieId){
        $updateModel = DB::table($this->update_info_table)->where($this->signByField,$movieId)->get(['genres','cast','directors','writers','story_line','countries','release_date','id_movie','type_film']);
        if ($updateModel->isNotEmpty()){
            $this->localizing->translateMovie($updateModel[0],$movieId,$this->signByField);
        }
    }
}
