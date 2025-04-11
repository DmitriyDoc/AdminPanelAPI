<?php


namespace App\Http\Controllers\Parser;

use App\Events\CurrentPercentageEvent;
use App\Events\MovieSyncCurrentPercentage;
use App\Events\ParserReportEvent;
use App\Events\SyncCurrentPercentage;
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

            ParserController::$reportProgress['report']['finishInfo'][camelToSnake($this->typeFilm)][] = $movieId;
            event(new ParserReportEvent(ParserController::$reportProgress));
            Log::info(">>> LOCALIZING MOVIE ID FINISH",[$movieId]);
        }
    }
    public function parserStart($params) : void
    {
        event(new CurrentPercentageEvent(['percent'=>10,'action'=>__('parser.general_data_parser'),'color'=>'']));
        foreach ($this->idMovies as $id) {
            array_push($this->linksInfo, $this->domen . $this->imgUrlFragment . $id);
            array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='.$params['typeImages']);
            array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex/?contentTypes='.$params['typePosters']);
        }
        $this->linksGetter($this->linksInfo, 'getMoviesInfo');

        event(new CurrentPercentageEvent(['percent'=>30,'action'=>__('parser.images_id_parser'),'color'=>'']));
        $this->linksGetter($this->linksIdsImages, 'getIdImages', self::ID_PATTERN);

        event(new CurrentPercentageEvent(['percent'=>40,'action'=>__('parser.images_by_id_parser'),'color'=>'']));
        $this->createIdArrayAndGetImages($this->update_images_table, $this->linksImages, $this->idMovies);

        event(new CurrentPercentageEvent(['percent'=>50,'action'=>__('parser.posters_id_parser'),'color'=>'']));
        $this->linksGetter($this->linksIdsPosters, 'getIdImages',  self::ID_PATTERN);

        event(new CurrentPercentageEvent(['percent'=>70,'action'=>__('parser.posters_by_id_parser'),'color'=>'']));
        $this->createIdArrayAndGetImages($this->update_posters_table, $this->linksPosters, $this->idMovies );

        event(new CurrentPercentageEvent(['percent'=>90,'action'=>__('parser.localization_parser'),'color'=>'']));
        foreach ($this->idMovies as $id) {
            $this->localizing($id);
        }
        $this->idMovies = [];
        event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
    }
}
