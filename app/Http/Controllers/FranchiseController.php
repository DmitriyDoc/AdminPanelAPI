<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class FranchiseController extends Controller
{

    public function index(Request $request,$slugSect,$slugFran): array
    {
        $allowedSectionsNames = [
            0=>'yellow',
            1=>'green',
            2=>'blue',
            3=>'cyan',
            4=>'purple',
            5=>'black',
            6=>'brown',
            7=>'red',
        ];

        if (in_array($slugSect,$allowedSectionsNames)){
            $collectionFranchise = [];
            $collectionTitle = null;
            $franchiseArr = LocalizingFranchise::where('value',$slugFran)->get(['id','label'])->toArray();
            if (!empty($franchiseArr)){
                $collectionTitle = $franchiseArr[0]['label'];
                $moviesIds = CollectionsCategoriesPivot::where('franchise_id',$franchiseArr[0]['id'])->get(['id_movie','type_film'])->toArray();
                $TypeFilmArray = [];
                $collection = collect();
                $collectionResponse = [];
                array_walk($moviesIds, function($item, $key) use (&$TypeFilmArray) {
                    $TypeFilmArray[$item['type_film']][] = $item['id_movie'];
                });
                foreach ($TypeFilmArray as $key => $item){
                    $model = convertVariableToModelName('IdType',$key, ['App', 'Models']);
                    $collection->add($model->select('type_film','id_movie','title','year','created_at','updated_at')->whereIn('id_movie',$item)->with('assignPoster')->get()->all());
                }
                $collapsed = $collection->collapse();
                $sorted = $collapsed->sort();
                if ($sorted[0]){
                    $allowedSortFields = ['desc','asc'];
                    $allowedFilterFields = $model->getFillable();
                    $limit = $request->query('limit',50);
                    $sortDir = strtolower($request->query('spin','asc'));
                    $sortBy = $request->query('orderBy','updated_at');
                    $page = $request->query('page',1);
                    if (!in_array($sortBy,$allowedFilterFields)){
                        $sortBy = $allowedSortFields[0];
                    }
                    $collectionSort = $sorted->sortBy($sortBy)->forPage($page,$limit);
                    if (in_array($sortDir,$allowedSortFields)){
                        if ($sortDir == 'asc'){
                            $collectionSort = $collectionSort->sortByDesc($sortBy);
                        }
                    }
                    $collectionSortArr = $collectionSort->values()->toArray();
                    foreach ($collectionSortArr as $movieItem){
                        if ($movieItem['assign_poster']){
                            $idsPostersArr[$movieItem['assign_poster']['type_film']][] = $movieItem['assign_poster']['id_poster_original'];
                        }
                    }
                    if (!empty($idsPostersArr)){
                        $posterCollection = collect();
                        foreach ($idsPostersArr as $key => $item){
                            $model = convertVariableToModelName('Posters',$key, ['App', 'Models']);
                            $posterCollection->add($model->select('srcset','id_movie')->whereIn('id',$item)->get()->all());
                        }
                        $collapsedPosters = $posterCollection->collapse()->toArray();
                    }
                    foreach ($collectionSort->values()->toArray() as $k => $item) {
                        if (!empty($collapsedPosters)){
                            foreach ($collapsedPosters as $posterItem){
                                if ($item['id_movie'] == $posterItem['id_movie']){
                                    $img = explode(',',$posterItem['srcset'] ?? '');
                                    $collectionResponse['data'][$k]['poster'] = $img[0] ?? '';
                                }
                            }
                        }
                        $collectionResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                        $collectionResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                        $collectionResponse['data'][$k]['title'] = $item['title'] ?? '';
                        $collectionResponse['data'][$k]['year'] = $item['year'] ?? null;
                        $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                        $collectionResponse['data'][$k]['type_film'] = $item['type_film'] ?? '';
                    }
                    $collectionResponse['title'] = $collectionTitle;
                    $collectionResponse['total'] = $collapsed->count();

                    return $collectionResponse;
                }
            }

        }
        return [];
    }

    public function list(Request $request): array
    {
        $model = convertVariableToModelName('LocalizingFranchise','', ['App', 'Models']);
        $franchiseCollection =  $model->all();
        if ($franchiseCollection->isNotEmpty()){
            $itemsCount = $franchiseCollection->count()??0;
            $allowedSortFields = ['desc','asc'];
            $allowedFilterFields = $model->getFillable();
            $limit = $request->query('limit',50);
            $page = $request->query('page',1);
            $sortDir = strtolower($request->query('spin','asc'));
            $sortBy = $request->query('orderBy','created_at');
            if (!in_array($sortBy,$allowedFilterFields)){
                $sortBy = $allowedSortFields[0];
            }
            $collectionSort = $franchiseCollection->sortBy($sortBy)->forPage($page,$limit);
            if (in_array($sortDir,$allowedSortFields)){
                if ($sortDir == 'asc'){
                    $collectionSort = $collectionSort->sortByDesc($sortBy);
                }
            }
            $collectionSortArr = $collectionSort->values()->toArray();
            return [
                'total' => $itemsCount,
                'data' => $collectionSortArr
            ];
        }
        return [];
    }

    public function destroy(Request $request)
    {
        $data = Validator::make($request->all(),[
            'id' => 'required|int',
        ])->safe()->all();
        if (!empty($data)){
            transaction( function () use ($data){
                CollectionsCategoriesPivot::where('franchise_id',$data['id'])->update([
                    'franchise_id'=>null
                ]);
                CollectionsFranchisesPivot::find($data['id'])->delete();
                LocalizingFranchise::find($data['id'])->delete();
            });
        }
    }
}
