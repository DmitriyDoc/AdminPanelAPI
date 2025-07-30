<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CollectionsCategoriesPivot;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;


class SectionsController extends Controller
{

    public function index(Request $request,$slug): array
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
        $currentLocale = Lang::locale();
        if (in_array($slug,$allowedSectionsNames)){
            $section = Category::where('value',$slug)->with('children')->first()->toArray();
            foreach ($section['children'] as $collection) {
                $collectionIds[] = $collection['id'];
            }
            $pivot = CollectionsCategoriesPivot::query();
            $moviesIds = $pivot->whereIn('collection_id',$collectionIds)->get(['id_movie','type_film'])->toArray();

            $typeFilmArray = [];
            $collection = collect();
            $collectionResponse = [];

            if (!empty($moviesIds)){
                array_walk($moviesIds, function($item, $key) use (&$typeFilmArray) {
                    //$typeFilm = getTableSegmentOrTypeId($item['type_film']);
                    //$typeFilmArray[$typeFilm][] = $item['id_movie'];
                    $typeFilmArray[] = $item['id_movie'];
                });
                $model = modelByName('MovieInfo');
                $allowedFilterFields = $model->getFillable();
                $titleFieldName = transformTitleByLocale();

                if ($query = $request->query('search')){
                    $searchQuery = trim(strtolower(strip_tags($query)));
                    $model = $model->whereIn('id_movie',$typeFilmArray)->where($allowedFilterFields[1],'like','%'.$searchQuery.'%')->orWhere($allowedFilterFields[3],'like','%'.$searchQuery.'%');
                }

                $collection->add($model->select('type_film','id_movie',$titleFieldName,'published','year_release','created_at','updated_at')->whereIn('id_movie',$typeFilmArray)->with(['assignPoster','categories'])->get()->all());
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

                    if (in_array($sortDir,$allowedSortFields)){
                        if ($sortDir == 'desc'){
                            $sorted = $sorted->sortByDesc($sortBy);
                        } elseif($sortDir == 'asc'){
                            $sorted = $sorted->sortBy($sortBy);
                        }
                    }
                    $collectionSort = $sorted->forPage($perPage,$limit);
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
                        if (!empty($section)){
                            foreach ($section['children'] as $col){
                                foreach ($item['categories'] as $key => $cat){
                                    if (!empty($cat['collection_id'] )){
                                        if ($cat['collection_id'] == $col['id'] ){
                                            $collectionResponse['data'][$k]['collection'][$key]['label'] = $col['label_'.$currentLocale];
                                            $collectionResponse['data'][$k]['collection'][$key]['value'] = $col['value'];
                                        }
                                    }
                                }
                            }
                        }
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
                        $collectionResponse['data'][$k]['title'] = $item['title']??$item['original_title'];
                        $collectionResponse['data'][$k]['published'] = statusSelection($item['published']) ?? [];
                        $collectionResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                        $collectionResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                        $collectionResponse['data'][$k]['year'] = $item['year_release'] ?? null;
                        $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                        $collectionResponse['data'][$k]['type_film'] = getTableSegmentOrTypeId($item['type_film']) ?? '';
                    }
                    unset($hashedIds);
                    unset($previewImagesApi);
                    foreach ($section['children'] as $k => $item){
                        $collectionResponse['collections'][$k]['label'] = $item['label_'.$currentLocale];
                        $collectionResponse['collections'][$k]['value'] = $item['value'];
                    }

                }
                $collectionResponse['total'] = $collapsed->count();
                $collectionResponse['locale'] = LanguageController::localizingSectionsList();
                $collectionResponse['title'] = $section['title_'.$currentLocale];
                return $collectionResponse;
            }
        }
        return [];
    }

    public function destroy(Request $request)
    {
        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
        ])->safe()->all();
        if (!empty($data)){
            CollectionsCategoriesPivot::where('id_movie',$data['id_movie'])->delete();
        }

    }
}
