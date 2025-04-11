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

        }
        $modelArr['locale'] = LanguageController::localizingPersonsList();
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
    public function show(string $id)
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

        if (!empty($id)) {
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
                                $type = getTableSegmentOrTypeId($res['type_film']);
                                $knownFor[$index]['id_movie'] = $res['id_movie'];
                                $knownFor[$index]['type_film'] = __('movies.type_movies.'.$type);
                                $knownFor[$index]['type_film_slug'] = $type;
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
                $modelArr['locale'] = LanguageController::localizingPersonShow();
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
        $dataRequest = $request->all();
        $match = preg_match("/nm\d{1,10}/",$dataRequest['data']['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['data']['id'] : null;
        $itemsArr = ($dataRequest['data']['id_items']) && is_array($dataRequest['data']['id_items']) ? $dataRequest['data']['id_items'] : [];
        $tabIndex = $dataRequest['data']['tab_index'];

        if (!empty($id) && !empty($itemsArr)){
            transaction( function () use ($tabIndex,$itemsArr,$id){
                $celebsModelNames = [
                    0 => 'CelebsInfoEn',
                    1 => 'CelebsInfoRu'
                ];
                foreach ($celebsModelNames as $name){
                   $model = modelByName($name)->where('id_celeb',$id);
                   $collection =  $model->get(['filmography','id']);
                   if ($collection->isNotEmpty()){
                       $filmographyDecodeArr = json_decode($collection->first()->filmography,true);
                       foreach ($filmographyDecodeArr as $occupIndex => $occupation){
                           foreach ($occupation as $index => $key){
                               foreach ($itemsArr as $delId){
                                   if ($index == $delId && $occupIndex == $tabIndex) {
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
    public function destroy(Request $request)
    {
        $types = [
            0=>'CelebsInfo',
            1=>'CelebsInfoEn',
            2=>'CelebsInfoRu',
            3=>'ImagesCelebs',
        ];
        $dataRequest = $request->all();
        $match = preg_match("/nm\d{1,10}/",$dataRequest['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['id'] : null;
        if (!empty($id)) {
            $modelInfo = convertVariableToModelName('CelebsInfo','', ['App', 'Models']);
            $modelExist = $modelInfo::where('id_celeb',$id)->first();
            if ($modelExist){
                transaction( function () use ($id,$types){
                    foreach ($types as $type){
                        $model = convertVariableToModelName($type,'', ['App', 'Models']);
                        $model::where('id_celeb',$id)->delete();
                    }
                });
            }
        }
    }
}
