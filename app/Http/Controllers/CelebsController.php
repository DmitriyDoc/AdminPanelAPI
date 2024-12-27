<?php

namespace App\Http\Controllers;

use App\Models\MovieInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class CelebsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
//        $tableName = request()->segment(3) ?? '';
//        $allowedTableNames = [
//            0=>'FeatureFilm',
//            1=>'MiniSeries',
//            2=>'ShortFilm',
//            3=>'TvMovie',
//            4=>'TvSeries',
//            5=>'TvShort',
//            6=>'TvSpecial',
//            7=>'Video',
//            8=>'Celebs',
//        ];
//        if (!in_array($tableName,$allowedTableNames)){
//            $tableName = $allowedTableNames[0];
//        }


        $model = modelByName('CelebsInfo'.ucfirst(Lang::locale()));


        $allowedSortFields = ['desc','asc'];
        $allowedFilterFields = $model->getFillable();
        $limit = $request->query('limit',50);
        $sortDir = strtolower($request->query('spin','desc'));
        $sortBy = $request->query('orderBy','id');

        if (!in_array($sortBy,$allowedFilterFields)){
            $sortBy = $allowedFilterFields[0];
        }

        if (!in_array($sortDir,$allowedSortFields)){
            $sortDir = $allowedSortFields[0];
        }


        if ($request->query('search')){
            $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
            $model = $model->where('nameActor','like',"{$searchQuery}%")->orWhere('id_celeb','like',"{$searchQuery}%");
        }

        $modelArr = $model->select(['id_celeb', 'nameActor'])->with('info')->orderBy($sortBy,$sortDir)->paginate($limit)->toArray();

        if (!empty($modelArr['data'])){
            foreach ($modelArr['data'] as $k => $item) {
                if (!empty($item['info'])){
                    $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['info']['created_at'])) ?? '';
                    $modelArr['data'][$k]['poster'] = $item['info']['photo'] ?? '';
                }
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
            $model = modelByName('CelebsInfo'.ucfirst(Lang::locale()));
            $modelArr = $model->where('id_celeb',$id)->with('info')->first()->toArray();

            if (!empty($modelArr)){
                if (!empty($modelArr['info']['knowfor'])){
                    $knownForArr = json_decode($modelArr['info']['knowfor']);
                    foreach ($allowedTableNames as $type){
                        $relationPosterName = 'poster'.$type;
                        $relationPosterNameSnake = 'poster_'.camelToSnake($type);
                        foreach ($knownForArr as $index => $id) {
                            $res = MovieInfo::with($relationPosterName)->where('type_film',getTableSegmentOrTypeId($type))->where('id_movie',$id)->first();
                            if (!empty($res)){
                                $res = $res->toArray();
                                $knownFor[$index]['id_movie'] = $res['id_movie'];
                                $knownFor[$index]['type_film'] = getTableSegmentOrTypeId($res['type_film']);
                                $knownFor[$index]['title'] = $res['title'];
                                $knownFor[$index]['original_title'] = $res['original_title'];
                                $knownFor[$index]['poster'] = '';
                                if (!empty($res[$relationPosterNameSnake])){
                                    $knownFor[$index]['poster'] = $res[$relationPosterNameSnake][0]['src']??'';
                                }
                            }
                        }
                    }
                }

                $modelArr['info']['knowfor'] = $knownFor;
                $modelArr['filmography'] = json_decode($modelArr['filmography'],true) ?? [];
                $filmographyArr = [];
                foreach ($modelArr['filmography'] as $k => &$occupation){
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
                $modelArr['filmography'] = $filmographyArr;
                $modelArr['info']['created_at'] = date('Y-m-d', strtotime($modelArr['info']['created_at'])) ?? '';
                $modelArr['info']['updated_at'] = date('Y-m-d', strtotime($modelArr['info']['updated_at'])) ?? '';

                return $modelArr;
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
