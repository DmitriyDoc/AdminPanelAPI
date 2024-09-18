<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ParserUpdateMovieController extends ParserController
{

    public function parseMovies($params, $currentDate): void
    {
        $this->signByField = 'id_movie';
        $this->imgUrlFragment = '/title/';
        $this->chunkSize = 10;

        $this->insert_id_table = 'movies_id_type_' . $params['segment'];
        $this->update_info_table = 'movies_info_' . $params['segment'];
        $this->update_id_images_table = 'movies_id_images_' . $params['segment'];
        $this->update_id_posters_table = 'movies_id_posters_' . $params['segment'];
        $this->update_images_table = 'movies_images_' . $params['segment'];
        $this->update_posters_table = 'movies_posters_' . $params['segment'];

        if (empty($this->idMovies)) {
            DB::table($this->insert_id_table)->where('created_at', '>=', $currentDate)->orderBy('id')->chunk(30, function ($ids) {
                foreach ($ids as $id) {
                    array_push($this->idMovies, $id->{$this->signByField});
                }
            });
        }

        if (!empty($this->idMovies)) {
            foreach ($this->idMovies as $id) {
                array_push($this->linksInfo, $this->domen . $this->imgUrlFragment . $id);
                array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='.$params['typeImages']);
                array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='.$params['typePosters']);
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
        $allowedTableNames = [
            0=>'MiniSeries',
            1=>'TvSeries',
            2=>'TvMovie',
            3=>'Video',
            4=>'TvSpecial',
            5=>'TvShort',
            6=>'ShortFilm',
        ];
        if ($data = $request->all()){
            if ($request->session()->missing('syncMoviePercentageBar')) {
                $request->session()->put('syncMoviePercentageBar', 0);
                session()->save();
            }
            $model = convertVariableToModelName('IdType', $data['data']['type'], ['App', 'Models']);
            if (!$model::where('id_movie',$data['data']['id'])->exists()){
                foreach ($allowedTableNames as $type){
                    $model = convertVariableToModelName('IdType', $type, ['App', 'Models']);
                    if ($model::where('id_movie',$data['data']['id'])->exists()) break;
                }
            }
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
                        array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='. $data['data']['posterType']);
                    }
                    $request->session()->put('syncMoviePercentageBar', 10);
                    session()->save();
                    $this->linksGetter($this->linksInfo, 'getMoviesInfo');

                    $request->session()->put('syncMoviePercentageBar', 30);
                    session()->save();
                    $this->linksGetter($this->linksIdsImages, 'getIdImages', $this->update_id_images_table, self::ID_PATTERN);

                    $request->session()->put('syncMoviePercentageBar', 40);
                    session()->save();
                    $this->linksGetter($this->linksIdsPosters, 'getIdImages', $this->update_id_posters_table, self::ID_PATTERN);

                    $request->session()->put('syncMoviePercentageBar', 50);
                    session()->save();
                    $this->createIdArrayAndGetImages($this->update_id_posters_table, $this->update_posters_table, $this->linksPosters, $this->idMovies);
                    $this->createIdArrayAndGetImages($this->update_id_images_table, $this->update_images_table, $this->linksImages, $this->idMovies);

                    $request->session()->put('syncMoviePercentageBar', 80);
                    session()->save();
                    $this->localizing($id);
                    $this->touchDB($model, $data['data']['id'],$this->signByField);
                    $this->idMovies = [];

                    $request->session()->put('syncMoviePercentageBar', 100);
                    session()->save();
                }
            }
            return ['success'=>true];
        }
    }
    public function localizing($movieId){
        $updateModel = DB::table($this->update_info_table)->where($this->signByField,$movieId)->get(['genres','cast','directors','writers','story_line','countries','release_date','id_movie','type_film']);
        if ($updateModel->isNotEmpty()){
            $this->localizing->translateMovie($updateModel[0],$movieId,$this->signByField);
        }
    }
}
