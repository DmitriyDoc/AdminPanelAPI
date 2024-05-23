<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\IdTypeFeatureFilm;
use Illuminate\Http\Request;
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

        $model = convertVariableToModelName('IdType',$tableName, ['App', 'Models']);
        $allowedSortFields = ['desc','asc'];

        $allowedFilterFields = $model->getFillable();

        $limit = $request->query('limit',50);
        $sortDir = strtolower($request->query('spin','desc'));
        $sortBy = $request->query('orderBy','updated_at');
        if (!in_array($sortBy,$allowedFilterFields)){
            $sortBy = $allowedSortFields[0];
        }
        if (!in_array($sortDir,$allowedSortFields)){
            $sortDir = $allowedSortFields[0];
        }
        if ($request->has('search')){
            $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
            $model = $model->where($allowedFilterFields[1],'like','%'.$searchQuery.'%')->orWhere($allowedFilterFields[0],'like','%'.$searchQuery.'%');
        }

        $modelArr = $model->with('poster')->orderBy($sortBy,$sortDir)->paginate($limit)->toArray();

        if (!empty($modelArr['data'])){
            foreach ($modelArr['data'] as $k => $item) {
                $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                $modelArr['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                $img = explode(',',$item['poster']['srcset'] ?? '');
                $modelArr['data'][$k]['poster'] = $img[0] ?? '';
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

//            foreach ($allowedTableNames as $table){
//                $model = convertVariableToModelName('IdType', $table, ['App', 'Models']);
//                $res = $model::select('type')->where('id_movie',$id)->get()->toArray();
//                if (!empty($res)){
//                    $modelInfo = convertVariableToModelName('Info', $res[0]['type'], ['App', 'Models']);
//                    $modelArr = $modelInfo::with('poster')->where('id_movie',$id)->get()->toArray();
//                    break;
//                }
//            }
//            dd($modelArr);

        if (!in_array($slug,$allowedTableNames)){
            $slug = 'all';
        }

        if ($slug != 'all') {
            $model = convertVariableToModelName('Info', $slug, ['App', 'Models']);
            $modelArr = $model::with('poster','collection')->where('id_movie',$id)->get()->toArray();
        } else {
            foreach ($allowedTableNames as $type){
                $model = convertVariableToModelName('IdType', $type, ['App', 'Models']);
                $modelArr = $model::with(['info','poster'])->where('id_movie',$id)->get()->toArray();
                if (!empty($modelArr)) break;
            }
        }
        if (!empty($modelArr)){
            $infoMovieData = $modelArr[0]['info'] ?? $modelArr[0] ?? [];
            $infoMovieData['genres'] = (object) unset_serialize_key(unserialize($modelArr[0]['genres'] ?? $modelArr[0]['info']['genres'] ?? null)) ?? [];
            $infoMovieData['cast'] = unset_serialize_key(unserialize($modelArr[0]['cast'] ?? $modelArr[0]['info']['cast'] ?? null)) ?? [];
            $infoMovieData['directors'] = unset_serialize_key(unserialize($modelArr[0]['directors'] ?? $modelArr[0]['info']['directors'] ?? null)) ?? [];
            $infoMovieData['writers'] = (object) unset_serialize_key(unserialize($modelArr[0]['writers'] ?? $modelArr[0]['info']['writers'] ?? null)) ?? [];
            $infoMovieData['countries'] = (object) unset_serialize_key(unserialize($modelArr[0]['countries'] ?? $modelArr[0]['info']['countries'] ?? null))  ?? [];
            $infoMovieData['companies'] = (object) unset_serialize_key(unserialize($modelArr[0]['companies'] ?? $modelArr[0]['info']['companies'] ?? null)) ?? [];
            $infoMovieData['created_at'] = date('Y-m-d', strtotime($modelArr[0]['created_at'] ?? $modelArr[0]['info']['created_at'] ?? null)) ?? '';
            $infoMovieData['updated_at'] = date('Y-m-d', strtotime($modelArr[0]['updated_at'] ?? $modelArr[0]['info']['updated_at'] ?? null)) ?? '';
            $img = explode(',',$modelArr[0]['poster']['srcset'] ?? '');
            $infoMovieData['poster'] = $img[0] ?? '';
            if (!empty( $modelArr[0]['collection'])) {
                $collection = Collection::with('category')->find($modelArr[0]['collection'][0]['collection_id'])->toArray();
                $infoMovieData['collection']['id'] = $modelArr[0]['collection'][0]['franchise_id'] ? $modelArr[0]['collection'][0]['franchise_id'] : $modelArr[0]['collection'][0]['collection_id'];
                $infoMovieData['collection']['label'] = $collection['label'] ?? null;
                $infoMovieData['collection']['category_value'] = $collection['category'][0]['value'] ?? null;
                $infoMovieData['collection']['viewed'] = (bool) $modelArr[0]['collection'][0]['viewed'] ?? false;
                $infoMovieData['collection']['short'] = (bool) $modelArr[0]['collection'][0]['short'] ?? false;

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

        if (in_array($slug,$allowedTableNames)){
            if ($data = $request->data)
            transaction( function () use ($data,$id,$slug){
                $modelInfo = convertVariableToModelName('Info', $slug, ['App', 'Models']);
                $modelIdType = convertVariableToModelName('IdType', $slug, ['App', 'Models']);
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
