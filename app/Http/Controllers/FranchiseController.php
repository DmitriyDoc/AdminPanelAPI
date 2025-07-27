<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;


class FranchiseController extends Controller
{

    public function index(Request $request,$slugSect,$slugFran): array
    {
        $hashedIds = [];
        $apiPreviewLinks = [];
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
            $collectionFranchise = [];
            $collectionTitle = null;
            $titleFieldName = transformTitleByLocale();
            $currentLocaleLabel = 'label_'.Lang::locale();
            $franchiseArr = LocalizingFranchise::where('value',$slugFran)->first(['id',$currentLocaleLabel])->toArray();
            if (!empty($franchiseArr)){
                $collectionTitle = $franchiseArr[$currentLocaleLabel];
                $moviesIds = CollectionsCategoriesPivot::where('franchise_id',$franchiseArr['id'])->get(['id_movie','type_film'])->toArray();
                $TypeFilmArray = [];
                $collection = collect();
                $collectionResponse = [];
                array_walk($moviesIds, function($item, $key) use (&$TypeFilmArray) {
                    $TypeFilmArray[] = $item['id_movie'];
                });
                $model = modelByName('MovieInfo');
                $allowedFilterFields = $model->getFillable();

                if ($query = $request->query('search')){
                    $searchQuery = trim(strtolower(strip_tags($query)));
                    $model = $model->whereIn('id_movie',$TypeFilmArray)->where($allowedFilterFields[1],'like','%'.$searchQuery.'%')->orWhere($allowedFilterFields[3],'like','%'.$searchQuery.'%');
                }

                $collection->add($model->select('type_film','id_movie',$titleFieldName,'year_release','created_at','updated_at')->whereIn('id_movie',$TypeFilmArray)->with(['assignPoster','categories'])->get()->all());
                $collapsed = $collection->collapse();
                $sorted = $collapsed->sort();
                if ($sorted->isNotEmpty()){
                    $allowedSortFields = ['desc','asc'];
                    $limit = $request->query('limit',50);
                    $sortDir = strtolower($request->query('spin','asc'));
                    $sortBy = $request->query('orderBy','updated_at');
                    $perPage = $request->query('page',1);
                    if (!empty($searchQuery)){
                        $perPage = 1;
                    }
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
                    foreach ($collectionSortArr as $movieItem){
                        $hasher = new IdHasher($movieItem['id_movie']);
                        $hashedIds[$hasher->getResult()??''][] = ['old_id'=>$movieItem['id_movie']];
                        $hashedIds['api'][] = $hasher->getResult();
                    }

                    if (!empty($hashedIds)){
                        $data = ['movieIds' => $hashedIds['api']];
                        $apiService = new ApiRequestImages();
                        $previewImagesApi = $apiService->sendApiRequest(env('API_HOST_URL')."/api/images/batch/types/original_poster/small", 'POST', $data, true);
                        if ($previewImagesApi['status'] === 200 && $previewImagesApi['data']['success']){
                            unset($hashedIds['api']);
                            foreach ($previewImagesApi['data']['images'] as $k =>$image){
                                if (!empty($image)){
                                    $apiPreviewLinks[$k] = $image;
                                }
                            }

                        }
                    }
                    foreach ($collectionSort->values()->toArray() as $k => $item) {
                        $typeKey = getTableSegmentOrTypeId($item['type_film']);
                        if (!empty($collapsedPosters)){
                            foreach ($hashedIds as $hashKey => $ids) {
                                foreach ($collapsedPosters as $posterItem){
                                    if ($item['id_movie'] == $posterItem['id_movie']){
                                        $img = explode(',',$posterItem['srcset'] ?? '');
                                        if ($posterItem['id_movie'] == $ids[0]['old_id']){
                                            $collectionResponse['data'][$k]['poster'] = (!empty($previewImagesApi['data']['images'][$hashKey])) ? $previewImagesApi['data']['images'][$hashKey][0]['url'] : $img[0] ?? '';
                                        }
                                    }
                                }
                            }

                        }
                        $collectionResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                        $collectionResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                        $collectionResponse['data'][$k]['title'] = $item['title']??$item['original_title'];
                        $collectionResponse['data'][$k]['year'] = $item['year_release'] ?? null;
                        $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                        $collectionResponse['data'][$k]['type_film'] = __('movies.type_movies.'.$typeKey) ?? '';
                        $collectionResponse['data'][$k]['type_film_link'] = $typeKey;
                    }
                    unset($hashedIds);
                    unset($previewImagesApi);
                }
                $collectionResponse['title'] = $collectionTitle;
                $collectionResponse['total'] = $collapsed->count();
                $collectionResponse['locale'] = LanguageController::localizingFranchisesList();
                return $collectionResponse;
            }

        }
        return [];
    }

    public function list(Request $request): array
    {
        $model = convertVariableToModelName('LocalizingFranchise','', ['App', 'Models']);
        $franchiseCollection =  $model->all();
        if ($franchiseCollection->isNotEmpty()){
            $allowedFilterFields = $model->getFillable();
            if ($request->has('search')){
                $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
                if (!empty($searchQuery)){
                    $franchiseCollection = $franchiseCollection->where('value',$searchQuery);
                }
            }
            $itemsCount = $franchiseCollection->count()??0;
            $allowedSortFields = ['desc','asc'];
            $limit = $request->query('limit',50);
            $page = $request->query('page',1);
            $sortDir = strtolower($request->query('spin','asc'));
            $sortBy = $request->query('orderBy','created_at');
            if (!in_array($sortBy,$allowedFilterFields)){
                $sortBy = $allowedSortFields[0];
            }
            if (in_array($sortDir,$allowedSortFields)){
                $sortDir == 'desc' ? $franchiseCollection = $franchiseCollection->sortByDesc($sortBy) : $franchiseCollection->sortBy($sortBy);
            }
            $collectionSort = $franchiseCollection->forPage($page,$limit);
            $collectionSortArr = $collectionSort->values()->toArray();
            return [
                'total' => $itemsCount,
                'data' => $collectionSortArr,
                'locale' => LanguageController::localizingFranchisesInfoList()
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
