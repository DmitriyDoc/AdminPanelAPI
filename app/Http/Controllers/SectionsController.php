<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CollectionsCategoriesPivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class SectionsController extends Controller
{

    public function index(Request $request,$slug): array
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

        if (in_array($slug,$allowedSectionsNames)){
            $section = Category::where('value',$slug)->with('children')->get()->toArray();
            foreach ($section[0]['children'] as $collection) {
                $collectionIds[] = $collection['id'];
            }
            $pivot = CollectionsCategoriesPivot::query();
            $moviesIds = $pivot->whereIn('collection_id',$collectionIds)->get(['id_movie','type_film'])->toArray();

            $TypeFilmArray = [];
            $collection = collect();
            $collectionResponse = [];

            array_walk($moviesIds, function($item, $key) use (&$TypeFilmArray) {
                $TypeFilmArray[$item['type_film']][] = $item['id_movie'];
            });

            foreach ($TypeFilmArray as $key => $item){
                $model = convertVariableToModelName('IdType',$key, ['App', 'Models']);
                $allowedFilterFields = $model->getFillable();
                if ($request->has('search')){
                    $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
                    $model = $model->whereIn('id_movie',$item)->where($allowedFilterFields[2],'like','%'.$searchQuery.'%')->orWhere($allowedFilterFields[1],'like','%'.$searchQuery.'%');
                }
                $collection->add($model->select('type_film','id_movie','title','year','created_at','updated_at')->whereIn('id_movie',$item)->with(['assignPoster','categories'])->get()->all());
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
                    if (!empty($section[0])){
                        foreach ($section[0]['children'] as $col){
                            foreach ($item['categories'] as $key => $cat){
                                if (!empty($cat['collection_id'] )){
                                    if ($cat['collection_id'] == $col['id'] ){
                                        $collectionResponse['data'][$k]['collection'][$key]['label'] = $col['label'];
                                        $collectionResponse['data'][$k]['collection'][$key]['value'] = $col['value'];
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
                    $collectionResponse['data'][$k]['year'] = $item['year'] ?? null;
                    $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                    $collectionResponse['data'][$k]['type_film'] = $item['type_film'] ?? '';
                }
                $collectionResponse['title'] = $section[0]['title'];
                $collectionResponse['total'] = $collapsed->count();
                $collectionResponse['collections'] = $section[0]['children'];
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
