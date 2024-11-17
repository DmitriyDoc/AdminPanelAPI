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
            $this->parserStart([
                'typeImages'=>$params['typeImages'],
                'typePosters'=>$params['typePosters'],
            ]);
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
            7=>'FeatureFilm',
        ];
        if ($data = $request->all()){
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
                    $this->parserStart([
                        'typeImages'=>'still_frame',
                        'typePosters'=>$data['data']['posterType'],
                        ]);
                }
                $this->touchDB($model, $data['data']['id'],$this->signByField);
                return ['success' => true];
            }
        }
    }
    public function localizing($movieId)
    {
        $updateModel = DB::table($this->update_info_table)->where($this->signByField,$movieId)->get(['genres','cast','directors','writers','story_line','countries','release_date','id_movie','type_film']);
        if ($updateModel->isNotEmpty()){
            $this->localizing->translateMovie($updateModel[0],$movieId,$this->signByField);
            session()->push('tracking.report.finishLocalizing', $movieId);
            session()->save();
            Log::info(">>> LOCALIZING MOVIE ID FINISH",[$movieId]);
        }
    }
    public function parserStart($params):void
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
        $this->linksGetter($this->linksIdsImages, 'getIdImages', $this->update_id_images_table, self::ID_PATTERN);

        session(['tracking.syncMoviePercentageBar' => 40]);
        session()->save();
        $this->linksGetter($this->linksIdsPosters, 'getIdImages', $this->update_id_posters_table, self::ID_PATTERN);

        session(['tracking.syncMoviePercentageBar' => 50]);
        session()->save();
        $this->createIdArrayAndGetImages($this->update_id_posters_table, $this->update_posters_table, $this->linksPosters, $this->idMovies);
        session(['tracking.syncMoviePercentageBar' => 70]);
        session()->save();
        $this->createIdArrayAndGetImages($this->update_id_images_table, $this->update_images_table, $this->linksImages, $this->idMovies);

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
