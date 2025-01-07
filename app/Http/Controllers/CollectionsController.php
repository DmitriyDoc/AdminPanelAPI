<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;


class CollectionsController extends Controller
{

    public function index(Request $request,$slugSect,$slugColl): array
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
            8=>'silver',
            9=>'tan',
        ];

        if (in_array($slugSect,$allowedSectionsNames)){
            $currentLocaleLabel = 'label_'.Lang::locale();
            $allowedCollectionsArray = [];
            $collectionFranchise = [];
            $collectionId = null;
            $collectionTitle = null;
            $sectionId = Category::where('value',$slugSect)->get('id')->toArray();
            $localizingFranchiseModel = LocalizingFranchise::query()->get();
            if ($sectionId[0]){
                $allowedCollectionsArray = Collection::where('category_id',$sectionId[0]['id'])->with('children')->get()->toArray();
                foreach ($allowedCollectionsArray as $k => $item){
                   if ($item['value'] == $slugColl) {

                       $collectionTitle = $item[$currentLocaleLabel];
                       $collectionId = $item['id'];
                       $collectionFranchise = $item['children'];
                   }
                }
                if (!empty($collectionId)){
                    $moviesIds = CollectionsCategoriesPivot::where('collection_id',$collectionId)->get(['id_movie','type_film'])->toArray();
                    $TypeFilmArray = [];
                    $collection = collect();
                    $collectionResponse = [];
                    array_walk($moviesIds, function($item, $key) use (&$TypeFilmArray) {
                        $typeFilm = getTableSegmentOrTypeId($item['type_film']);
                        $TypeFilmArray[$typeFilm][] = $item['id_movie'];
                    });
                    $model = modelByName('MovieInfo');
                    $allowedFilterFields = $model->getFillable();
                    foreach ($TypeFilmArray as $key => $item){
                        if ($query = $request->query('search')){
                            $searchQuery = trim(strtolower(strip_tags($query)));
                            $model = $model->whereIn('id_movie',$item)->where($allowedFilterFields[2],'like','%'.$searchQuery.'%')->orWhere($allowedFilterFields[1],'like','%'.$searchQuery.'%');
                        }
                        $collection->add($model->select('type_film','id_movie','title','year_release','created_at','updated_at')->whereIn('id_movie',$item)->with(['assignPoster','categories'])->get()->all());
                    }
                    $collapsed = $collection->collapse();
                    $sorted = $collapsed->sort();
                    if ($sorted[0]){
                        $allowedSortFields = ['desc','asc'];
                        $limit = $request->query('limit',50);
                        $sortDir = strtolower($request->query('spin','asc'));
                        $sortBy = $request->query('orderBy','updated_at');
                        $perPage = $request->query('page',1);
                        if (!in_array($sortBy,$allowedFilterFields)){
                            $sortBy = $allowedSortFields[0];
                        }
                        $collectionSort = $sorted->sortBy($sortBy)->forPage($perPage,$limit);
                        if (in_array($sortDir,$allowedSortFields)){
                            if ($sortDir == 'asc'){
                                $collectionSort = $collectionSort->sortByDesc($sortBy);
                            }
                        }
                        $collectionSortArr = $collectionSort->values()->toArray();
                        foreach ($collectionSortArr as $movieItem){
                            if ($movieItem['assign_poster']){
                                $idsPostersArr[getTableSegmentOrTypeId($movieItem['assign_poster']['type_film'])][] = $movieItem['assign_poster']['id_poster_original'];
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
                        foreach ($collectionSortArr as $k => $item) {
                            if (!empty($collectionFranchise)){
                                foreach ($item['categories'] as $key => $cat){
                                    foreach ($collectionFranchise as $col){
                                        if (!empty($cat['franchise_id']) ){
                                            if ($cat['franchise_id'] == $col['id']){
                                                $collectionResponse['data'][$k]['franchise'][$key]['label'] = $localizingFranchiseModel->find($col['id'])->$currentLocaleLabel;
                                                $collectionResponse['data'][$k]['franchise'][$key]['value'] = $localizingFranchiseModel->find($col['id'])->value;
                                            }
                                        }
                                    }
                                }
                            }
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
                            $collectionResponse['data'][$k]['year'] = $item['year_release'] ?? null;
                            $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                            $collectionResponse['data'][$k]['type_film'] = getTableSegmentOrTypeId($item['type_film']) ?? '';
                        }
                        $collectionResponse['title'] = $collectionTitle;
                        $collectionResponse['total'] = $collapsed->count();
                        $collectionResponse['locale'] = LanguageController::localizingCollectionsList();
                        if (!empty($collectionResponse['data'])){
                            foreach ($collectionResponse['data'] as $dataMovie){
                                if (!empty($dataMovie['franchise'])){
                                    $collectionResponse['franchise'][] = array_values($dataMovie['franchise'])[0];
                                }
                            }
                        }
                        if(!empty($collectionResponse['franchise'])){
                            $collectionResponse['franchise'] = array_unique($collectionResponse['franchise'],SORT_REGULAR);
                        }
                        return $collectionResponse;
                    }
                }
            }
        }
        return [];
    }

    public function list(Request $request): array
    {
        $model = convertVariableToModelName('Collection','', ['App', 'Models']);
        $CollectionCol =  $model->all();
        if ($CollectionCol->isNotEmpty()){
            $allowedFilterFields = $model->getFillable();
            if ($request->has('search')){
                $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
                if (!empty($searchQuery)){
                    $CollectionCol = $CollectionCol->where('value',$searchQuery);
                }
            }
            $itemsCount = $CollectionCol->count()??0;
            $allowedSortFields = ['desc','asc'];
            $limit = $request->query('limit',20);
            $page = $request->query('page',1);
            $sortDir = strtolower($request->query('spin','asc'));
            $sortBy = $request->query('orderBy','created_at');
            if (!in_array($sortBy,$allowedFilterFields)){
                $sortBy = $allowedSortFields[0];
            }
            if (in_array($sortDir,$allowedSortFields)){
                $sortDir == 'desc' ? $CollectionCol = $CollectionCol->sortByDesc($sortBy) : $CollectionCol->sortBy($sortBy);
            }
            $collectionSort = $CollectionCol->forPage($page,$limit);
            $collectionSortArr = $collectionSort->values()->toArray();
            return [
                'total' => $itemsCount,
                'data' => $collectionSortArr,
                'locale' => LanguageController::localizingCollectionsInfoList()
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
                CollectionsCategoriesPivot::where('collection_id',$data['id'])->delete();
                CollectionsFranchisesPivot::where('collection_id',$data['id'])->delete();
                Collection::find($data['id'])->delete();
            });
        }
    }
}
