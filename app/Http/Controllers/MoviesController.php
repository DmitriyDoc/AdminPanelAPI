<?php

namespace App\Http\Controllers;

use App\Models\IdTypeFeatureFilm;
use Illuminate\Http\Request;

class MoviesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $requst): array
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
        $limit = $requst->query('limit',50);
        $sortDir = strtolower($requst->query('spin','desc'));
        $sortBy = $requst->query('orderBy','created_at');
        if (!in_array($sortBy,$allowedFilterFields)){
            $sortBy = $allowedSortFields[0];
        }
        if (!in_array($sortDir,$allowedSortFields)){
            $sortDir = $allowedSortFields[0];
        }
        if ($requst->has('search')){
            $searchQuery = trim(strtolower(strip_tags($requst->query('search'))));
            $model = $model->where($allowedFilterFields[1],'like','%'.$searchQuery.'%')->orWhere($allowedFilterFields[0],'like','%'.$searchQuery.'%');
        }
        $modelArr = $model->with('poster')->orderBy($sortBy,$sortDir)->paginate($limit)->toArray();
        if (!empty($modelArr['data'])){
            foreach ($modelArr['data'] as $k => $item) {
                $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                $modelArr['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                $modelArr['data'][$k]['poster'] = $item['poster']['src'] ?? '';
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
            $modelArr = $model::with('poster')->where('id_movie',$id)->get()->toArray();
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
            $infoMovieData['poster'] = $modelArr[0]['poster']['src'] ?? '';

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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug, string $id)
    {
        dd($id);
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
