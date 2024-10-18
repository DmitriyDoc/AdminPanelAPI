<?php

namespace App\Http\Controllers;

use App\Models\InfoCelebs;
use App\Models\LocalizingCelebsInfo;
use Illuminate\Http\Request;

class CelebsController extends Controller
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
        array_push($allowedFilterFields,'id');

        $limit = $request->query('limit',50);
        $sortDir = strtolower($request->query('spin','desc'));
        $sortBy = $request->query('orderBy','created_at');
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
                $filmographyArr = [];
                foreach ($modelArr[0]['filmography'] as $k => &$occupation){
                    uasort($occupation, function ($a, $b){return ($a['year'] < $b['year']);});
                    foreach ($occupation as $id =>$dataArr){
                        $filmographyArr[$k][] = [
                            'id' => $id,
                            'role' => $dataArr['role'],
                            'year' => $dataArr['year'],
                            'title' => $dataArr['title'],
                        ];
                    }
                }
                $modelArr[0]['filmography'] = $filmographyArr;
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
    public function removeFromFilmography(Request $request){
        $data = $request->all();
        $celebsModelNames = [
            0 => 'InfoCelebs',
            1 => 'LocalizingCelebsInfo'
        ];

        if (!empty($data['data']['id']) && !empty($data['data']['id_items']) && !empty($data['data']['tab_index'])){
            transaction( function () use ($data,$celebsModelNames){
                foreach ($celebsModelNames as $name){
                   $model = modelByName($name)->where('id_celeb',$data['data']['id']);
                   $collection =  $model->get(['filmography','id']);
                   if ($collection->isNotEmpty()){
                       $filmographyDecodeArr = json_decode($collection->first()->filmography,true);
                       foreach ($filmographyDecodeArr as $occupIndex => $occupation){
                           foreach ($occupation as $index => $key){
                               foreach ($data['data']['id_items'] as $delId){
                                   if ($index == $delId && $occupIndex == $data['data']['tab_index']) {
                                       unset($filmographyDecodeArr[$occupIndex][$index]);
                                   }
                               }
                           }

                       }
                       $updateModel = $model->find($collection->first()->id);
                       $updateModel->filmography = json_encode($filmographyDecodeArr);
                       $updateModel->save();
                   }
                }
            });
        }
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
