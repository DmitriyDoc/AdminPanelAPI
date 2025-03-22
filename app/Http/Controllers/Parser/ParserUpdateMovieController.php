<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ParserUpdateMovieController extends ParserController
{

    public function parseMovies($params,$dataId): void
    {
        $this->signByField = 'id_movie';
        $this->typeFilm = snakeToCamel($params['segment'],true);
        $this->imgUrlFragment = '/title/';
        $this->chunkSize = 10;

        $this->update_info_table = 'movies_info';
        $this->update_en_info_table = 'localizing_info_movies_en';
        $this->update_id_images_table = 'movies_id_images_' . $params['segment'];
        $this->update_id_posters_table = 'movies_id_posters_' . $params['segment'];
        $this->update_images_table = 'movies_images_' . $params['segment'];
        $this->update_posters_table = 'movies_posters_' . $params['segment'];

        if (!empty($dataId)) {
            $this->idMovies = $dataId;
            $this->parserStart([
                'typeImages'=>$params['typeImages'],
                'typePosters'=>$params['typePosters'],
            ]);
        }
    }

    public function update(Request $request)
    {
        if ($data = $request->all()){
            $segment = camelToSnake($data['data']['type']);

            $this->signByField = 'id_movie';
            $this->typeFilm = $data['data']['type'];
            $this->imgUrlFragment = '/title/';
            $this->chunkSize = 1;

            $this->update_info_table = 'movies_info';
            $this->update_en_info_table = 'localizing_info_movies_en';
            $this->update_id_images_table = 'movies_id_images_' . $segment;
            $this->update_id_posters_table = 'movies_id_posters_' . $segment;
            $this->update_images_table = 'movies_images_' . $segment;
            $this->update_posters_table = 'movies_posters_' . $segment;

            if (empty($this->idMovies)) {
                array_push($this->idMovies,  $data['data']['id']);
            }
            if (!empty($this->idMovies)) {
                $this->parserStart([
                    'typeImages'=>'still_frame',
                    'typePosters'=>$data['data']['posterType'],
                    ]);
            }
        }
    }
    public function localizing($movieId)
    {
        $updateModel = DB::table($this->update_en_info_table)->where($this->signByField,$movieId)->first(['genres','cast','directors','writers','story_line','countries','release_date','id_movie']);

        if (!empty($updateModel)){
            $this->localizing->translateMovie($updateModel,$movieId,$this->signByField);
            session()->push('tracking.report.finishLocalizing', $movieId);
            session()->save();
            Log::info(">>> LOCALIZING MOVIE ID FINISH",[$movieId]);
        }
    }
    public function parserStart($params) : void
    {
        foreach ($this->idMovies as $id) {
            array_push($this->linksInfo, $this->domen . $this->imgUrlFragment . $id);
            array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='.$params['typeImages']);
            array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='.$params['typePosters']);
        }

        session(['tracking.syncMoviePercentageBar' => 10]);
        session()->save();
        $this->linksGetter($this->linksInfo, 'getMoviesInfo');

        session(['tracking.syncMoviePercentageBar' => 30]);
        session()->save();
        $this->linksGetter($this->linksIdsImages, 'getIdImages', self::ID_PATTERN);

        session(['tracking.syncMoviePercentageBar' => 40]);
        session()->save();
        $this->createIdArrayAndGetImages($this->update_images_table, $this->linksImages, $this->idMovies);

        session(['tracking.syncMoviePercentageBar' => 50]);
        session()->save();
        $this->linksGetter($this->linksIdsPosters, 'getIdImages',  self::ID_PATTERN);

        session(['tracking.syncMoviePercentageBar' => 70]);
        session()->save();
        $this->createIdArrayAndGetImages($this->update_posters_table, $this->linksPosters, $this->idMovies );

        session(['tracking.syncMoviePercentageBar' => 90]);
        session()->save();
        foreach ($this->idMovies as $id) {
            $this->localizing($id);
        }
        $this->idMovies = [];

        session(['tracking.syncMoviePercentageBar' => 100]);
        session()->save();
    }
}
