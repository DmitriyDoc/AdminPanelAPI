<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\IdImagesTrait;
use App\Traits\Components\ImagesTrait;
use App\Traits\Components\MoviesInfoTrait;
use App\Traits\ParserTrait;
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
                array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex?refine=still_frame');
                array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex?refine=poster');
            }
        }

        $this->linksGetter($this->linksInfo, 'getMoviesInfo');
        $this->linksGetter($this->linksIdsImages, 'getIdImages', $this->update_id_images_table, self::ID_PATTERN);
        $this->linksGetter($this->linksIdsPosters, 'getIdImages', $this->update_id_posters_table, self::ID_PATTERN);

        $this->createIdArrayAndGetImages($this->update_id_images_table, $this->update_images_table, $this->linksImages, $this->idMovies);
        $this->createIdArrayAndGetImages($this->update_id_posters_table, $this->update_posters_table, $this->linksPosters, $this->idMovies);
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
                        array_push($this->linksIdsImages, $this->domen . $this->imgUrlFragment . $id . '/mediaindex?refine=still_frame');
                        array_push($this->linksIdsPosters, $this->domen . $this->imgUrlFragment . $id . '/mediaindex?refine=poster');
                    }
                    $this->linksGetter($this->linksInfo, 'getMoviesInfo');
                    $this->linksGetter($this->linksIdsImages, 'getIdImages', $this->update_id_images_table, self::ID_PATTERN);
                    $this->linksGetter($this->linksIdsPosters, 'getIdImages', $this->update_id_posters_table, self::ID_PATTERN);

                    $this->createIdArrayAndGetImages($this->update_id_posters_table, $this->update_posters_table, $this->linksPosters, $this->idMovies);
                    $this->createIdArrayAndGetImages($this->update_id_images_table, $this->update_images_table, $this->linksImages, $this->idMovies);

                    $this->touchDB($model, $data['data']['id'],$this->signByField);
                }
            }
        }

    }
}
