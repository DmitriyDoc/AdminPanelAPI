<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\MovieInfo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class MoviesController extends Controller
{
    public $typeMovieSlug;
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

        $model = $model->select('id_movie', 'title','year_release','created_at','updated_at')->where('type_film',$typeId);

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
                $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                $modelArr['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                $img = explode(',',$item[$relationName][0]['srcset'] ?? '');

                $modelArr['data'][$k]['poster'] = $img[0] ?? '';
                unset($modelArr['data'][$k][$relationName]);
            }

            return $modelArr;
        }
        return [];
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
        $localazingName = 'localazing'.ucfirst(Lang::locale());
        $relationPosterName = camelToSnake($modelPosterName);
        $relationLocalizeName = camelToSnake($localazingName);
        $safeId = trim(strtolower(strip_tags($id)));
        $typeId = getTableSegmentOrTypeId($slug);
        $modelArr = $model::with([$modelPosterName,'collection',$localazingName])->where('type_film',$typeId)->where('id_movie',$safeId)->first()->toArray();

        if (empty($modelArr)){
            foreach ($allowedTableNames as $type){
                $modelArr = $model::with([$modelPosterName,'collection',$localazingName])->where('type_film',getTableSegmentOrTypeId($type))->where('id_movie',$safeId)->first()->toArray();
                if (!empty($modelArr)) break;
            }
        }

        if (!empty($modelArr)){
            $infoMovieData = $modelArr[$relationLocalizeName] ?? [];
            $infoMovieData['type_film'] = $slug;
            $infoMovieData['title'] = $modelArr['title'];
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

            if (!empty( $modelArr['collection'])) {
                foreach ($modelArr['collection'] as $key => $itemCollection) {
                    $collection = Collection::with('category')->find($itemCollection['collection_id'])->toArray();
                    $infoMovieData['collection']['id'][$key] = $itemCollection['franchise_id'] ? 'fr_'.$itemCollection['collection_id'].$itemCollection['franchise_id'] : $itemCollection['collection_id'];
                    $infoMovieData['collection']['catInfo'][$key]['label'] = $collection['label'] ?? null;
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
    public function update(Request $request, string $slug,string $id)
    {
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

        $this->typeMovieSlug = $slug;
        if (in_array($this->typeMovieSlug ,$allowedTableNames)){
            if ($data = $request->data)

                $modelIdType = convertVariableToModelName('IdType', $this->typeMovieSlug, ['App', 'Models']);
                if (!$modelIdType::where('id_movie',$id)->exists()){

                    foreach ($allowedTableNames as $type){
                        $modelIdType = convertVariableToModelName('IdType', $type, ['App', 'Models']);
                        if ($modelIdType::where('id_movie',$id)->exists()){
                            $this->typeMovieSlug  = $type;
                            break;
                        }
                    }
                }
            $modelInfo = convertVariableToModelName('Info', $this->typeMovieSlug, ['App', 'Models']);
            unset($this->typeMovieSlug);
            transaction( function () use ($data,$id,$modelInfo,$modelIdType){
                $modelInfo::where('id_movie',$id)->update($data);
                $modelIdType::where('id_movie',$id)->update([
                    'title' => $data['title'],
                    'year' => $data['year_release']
                ]);
            });

        }
        return ['success'=>true];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug, string $id)
    {
        $types = [
            0=>'IdType',
            1=>'Info',
            2=>'Images',
            3=>'IdImages',
            4=>'Posters',
            5=>'IdPosters',
        ];
        //$tableName = request()->segment(3) ?? '';
        if (!empty($slug)) {
            foreach ($types as $type){
                $model = convertVariableToModelName($type,$slug, ['App', 'Models']);
                $model::where('id_movie',$id)->delete();
            }
        }
    }
}
