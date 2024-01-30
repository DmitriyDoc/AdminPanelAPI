<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CelebsController extends Controller
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
                $modelArr['data'][$k]['poster'] = $item['poster']['photo'] ?? '';
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
        $knownFor = [];
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

        if (!empty($slug) &&  $slug == 'Celebs') {
            $model = convertVariableToModelName('Info', $slug, ['App', 'Models']);
            $modelArr = $model->where('id_celeb',$id)->get()->toArray();
            if (!empty($modelArr)){
                if (!empty($modelArr[0]['knowfor'])){
                    $knownForArr = json_decode($modelArr[0]['knowfor']);
                    foreach ($allowedTableNames as $type){
                        foreach ($knownForArr as $id) {
                            $model = convertVariableToModelName('IdType', $type, ['App', 'Models']);
                            $res = $model::with(['poster'])->where('id_movie',$id)->get()->toArray();
                            if (!empty($res)){
                                $knownFor[] = $res[0];
                            }
                        }
                    }
                }

                $modelArr[0]['knowfor'] = $knownFor;
                $modelArr[0]['filmography'] = json_decode($modelArr[0]['filmography'],true) ?? [];
                foreach ($modelArr[0]['filmography'] as &$occupation){
                    uasort($occupation, function ($a, $b){return ($a['year'] > $b['year']);});
                }
                $modelArr[0]['created_at'] = date('Y-m-d', strtotime($modelArr[0]['created_at'])) ?? '';
                $modelArr[0]['updated_at'] = date('Y-m-d', strtotime($modelArr[0]['updated_at'])) ?? '';

                return $modelArr[0];
            }
        }
        return [];
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
        ];
        //$tableName = request()->segment(3) ?? '';
        if (!empty($slug)) {
            foreach ($types as $type){
                $model = convertVariableToModelName($type,$slug, ['App', 'Models']);
                $model::where('id_celeb',$id)->delete();
            }
        }
    }
}
