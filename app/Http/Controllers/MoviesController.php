<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\MovieInfo;

use App\Models\MoviesInfoEn;
use App\Models\MoviesInfoRu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class MoviesController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        $tableName = request()->segment(3) ?? '';
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
            8=>'Celebs',
        ];
        if (!in_array($tableName,$allowedTableNames)){
            $tableName = $allowedTableNames[0];
        }

        $model = modelByName('MovieInfo');
        $modelPosterName = 'poster'.$tableName;
        $relationName = camelToSnake($modelPosterName);
        $typeId = getTableSegmentOrTypeId($tableName);
        $allowedSortFields = ['desc','asc'];
        $allowedFilterFields = $model->getFillable();
        $titleFieldName = transformTitleByLocale();
        $model = $model->select('id_movie', $titleFieldName,'year_release','created_at','updated_at')->where('type_film',$typeId);

        $limit = $request->query('limit',50);
        $sortDir = strtolower($request->query('spin','desc'));
        $sortBy = $request->query('orderBy','updated_at');
        if (!in_array($sortBy,$allowedFilterFields)){
            $sortBy = $allowedSortFields[0];
        }
        if (!in_array($sortDir,$allowedSortFields)){
            $sortDir = $allowedSortFields[0];
        }
        if ($query = $request->query('search')){
            $safeQuery = trim(strtolower(strip_tags($query)));
            $model = $model->where($allowedFilterFields[3],'like','%'.$safeQuery.'%')
                ->orWhere($allowedFilterFields[1],'like','%'.$safeQuery.'%');
        }

        $modelArr = $model->with($modelPosterName)->orderBy($sortBy,$sortDir)->paginate($limit)->toArray();

        if (!empty($modelArr['data'])){
            foreach ($modelArr['data'] as $k => $item) {
                $modelArr['data'][$k]['title'] = $item['title']??$item['original_title'];
                $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                $modelArr['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                $img = explode(',',$item[$relationName][0]['srcset'] ?? '');

                $modelArr['data'][$k]['poster'] = $img[0] ?? '';
                unset($modelArr['data'][$k][$relationName]);
            }
        }
        $modelArr['locale'] = LanguageController::localizingMoviesList();
        return $modelArr;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug, string $id)
    {
        $infoMovieData = [];
        $posterSrcSet = null;
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
        ];
        if (!in_array($slug,$allowedTableNames)){
            $slug = $slug[0];
        }
        $model = modelByName('MovieInfo');
        $modelPosterName = 'poster'.$slug;
        $titleFieldName = transformTitleByLocale();
        $localazingName = 'localazing'.ucfirst(Lang::locale());
        $currentLocaleLabel = 'label_'.Lang::locale();
        $relationPosterName = camelToSnake($modelPosterName);
        $relationLocalizeName = camelToSnake($localazingName);
        $safeId = trim(strtolower(strip_tags($id)));
        $typeId = getTableSegmentOrTypeId($slug);
        $modelArr = $model::with([$modelPosterName,'collection',$localazingName])->where('type_film',$typeId)->where('id_movie',$safeId)->first();

        if (empty($modelArr)){
            foreach ($allowedTableNames as $type){
                $modelArr = $model::with([$modelPosterName,'collection',$localazingName])->where('type_film',getTableSegmentOrTypeId($type))->where('id_movie',$safeId)->first();
                if (!empty($modelArr)) break;
            }
        }

        if (!empty($modelArr)){
            $modelArr = $modelArr->toArray();
            $infoMovieData = $modelArr[$relationLocalizeName] ?? [];
            $infoMovieData['type_film'] = __('movies.type_movies.'.$slug) ?? '';
            $infoMovieData['title'] = $modelArr[$titleFieldName];
            $infoMovieData['original_title'] = $modelArr['original_title'];
            $infoMovieData['year_release'] = $modelArr['year_release'];
            $infoMovieData['restrictions'] = $modelArr['restrictions'];
            $infoMovieData['runtime'] = $modelArr['runtime'];
            $infoMovieData['rating'] = $modelArr['rating'];
            $infoMovieData['budget'] = $modelArr['budget'];
            $infoMovieData['genres'] = json_decode($modelArr[$relationLocalizeName]['genres'] ??  null) ?? [];
            $infoMovieData['cast'] = json_decode($modelArr[$relationLocalizeName]['cast'] ?? null) ?? [];
            $infoMovieData['directors'] = json_decode( $modelArr[$relationLocalizeName]['directors'] ?? null) ?? [];
            $infoMovieData['writers'] = json_decode($modelArr[$relationLocalizeName]['writers'] ?? null) ?? [];
            $infoMovieData['countries'] = json_decode($modelArr[$relationLocalizeName]['countries'] ?? null)  ?? [];
            $infoMovieData['companies'] = (object) unset_serialize_key(unserialize($modelArr['companies'] ?? null)) ?? [];
            $infoMovieData['created_at'] = date('Y-m-d', strtotime($modelArr['created_at']  ?? null)) ?? '';
            $infoMovieData['updated_at'] = date('Y-m-d', strtotime($modelArr['updated_at']  ?? null)) ?? '';
            $infoMovieData['collection'] = $modelArr['collection'];
            if (!empty($modelArr[$relationPosterName])){
                $posterSrcSet = $modelArr[$relationPosterName][0]['srcset'];
                $assignPosterId = AssignPoster::where('id_movie',$safeId)->first('id_poster_original');

                if (!empty($assignPosterId)){
                    foreach ($modelArr[$relationPosterName] as $poster){
                        if ($poster['id'] == $assignPosterId->id_poster_original){
                            $posterSrcSet = $poster['srcset'];
                        }
                    }
                }
            }
            $img = explode(',',$posterSrcSet ?? '');
            $infoMovieData['poster'] = $img[0] ?? '';
            $infoMovieData['locale'] = LanguageController::localizingMovieShow();
            if (!empty( $modelArr['collection'])) {
                foreach ($modelArr['collection'] as $key => $itemCollection) {
                    $collection = Collection::with('category')->find($itemCollection['collection_id'])->toArray();
                    $infoMovieData['collection']['id'][$key] = $itemCollection['franchise_id'] ? 'fr_'.$itemCollection['collection_id'].$itemCollection['franchise_id'] : $itemCollection['collection_id'];
                    $infoMovieData['collection']['catInfo'][$key]['label'] = $collection[$currentLocaleLabel] ?? null;
                    $infoMovieData['collection']['catInfo'][$key]['category_value'] = $collection['category'][0]['value'] ?? null;
                }
                $infoMovieData['collection']['viewed'] = (bool) $modelArr['collection'][0]['viewed'] ?? false;
                $infoMovieData['collection']['short'] = (bool) $modelArr['collection'][0]['short'] ?? false;
                $infoMovieData['collection']['adult'] = (bool) $modelArr['collection'][0]['adult'] ?? false;
                unset( $infoMovieData['collection'][0]);
                unset($collection);

            }
            unset($modelArr);
        }

        return $infoMovieData;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $dataRequest = $request->all();
        $match = preg_match("/tt\d{1,10}/",$dataRequest['data']['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['data']['id'] : null;
        $dataForm = ($dataRequest['data']['form']) && is_array($dataRequest['data']['form']) ? $dataRequest['data']['form'] : [];
        $locale = $dataRequest['data']['lang'] === 'ru' || 'en' ? $dataRequest['data']['lang'] : '';

        if (!empty($dataForm) && $id){
            $model = modelByName('MovieInfo');
            $localizingModel = modelByName('MoviesInfo'.ucfirst($locale));
            transaction( function () use ( $dataForm, $id, $model, $localizingModel ){
                $model::where('id_movie',$id)->update([
                    'title' => $dataForm['title'],
                    'original_title' => $dataForm['original_title'],
                    'year_release' => $dataForm['year_release'],
                    'restrictions' => $dataForm['restrictions'],
                    'runtime' => $dataForm['runtime'],
                    'rating' => $dataForm['rating'],
                    'budget' => $dataForm['budget'],
                ]);
                $localizingModel::where('id_movie',$id)->update([
                    'release_date' => $dataForm['release_date'],
                    'story_line' => $dataForm['story_line']
                ]);
            });
            return ['success'=>true];
        }
        return ['success'=>false];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $dataRequest = $request->all();
        $match = preg_match("/tt\d{1,10}/",$dataRequest['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['id'] : null;
        $type = is_int(getTableSegmentOrTypeId($dataRequest['type'])) ? $dataRequest['type'] : null;

        $typesTables = [
            0=>'Images',
            1=>'IdImages',
            2=>'Posters',
            3=>'IdPosters',
        ];
        if (!empty($id) && !empty($type)) {
            transaction( function () use ( $typesTables, $id, $type ){
                MovieInfo::query()->where('id_movie',$id)->delete();
                MoviesInfoEn::query()->where('id_movie',$id)->delete();
                MoviesInfoRu::query()->where('id_movie',$id)->delete();
                AssignPoster::query()->where('id_movie',$id)->delete();
                foreach ($typesTables as $table){
                    $model = convertVariableToModelName($table,$type, ['App', 'Models']);
                    $model::where('id_movie',$id)->delete();
                }
            });
        }
    }
}
