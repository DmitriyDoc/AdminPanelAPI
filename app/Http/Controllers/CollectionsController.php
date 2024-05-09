<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use Illuminate\Http\Request;
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
        ];

        if (in_array($slugSect,$allowedSectionsNames)){
            $allowedCollectionsArray = [];
            $collectionFranchise = [];
            $collectionId = null;
            $collectionTitle = null;
            $sectionId = Category::where('value',$slugSect)->get('id')->toArray();
            if ($sectionId[0]){
                $allowedCollectionsArray = Collection::where('category_id',$sectionId[0]['id'])->with('children')->get()->toArray();
                foreach ($allowedCollectionsArray as $k => $item){
                   if ($item['value'] == $slugColl) {
                       $collectionTitle = $item['label'];
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
                        $TypeFilmArray[$item['type_film']][] = $item['id_movie'];
                    });
                    foreach ($TypeFilmArray as $key => $item){
                        $model = convertVariableToModelName('IdType',$key, ['App', 'Models']);
                        $collection->add($model->select('type_film','id_movie','title','created_at','updated_at')->whereIn('id_movie',$item)->with(['poster','categories'])->get()->all());
                    }
                    $collapsed = $collection->collapse();
                    $sorted = $collapsed->sort();
                    if ($sorted[0]){
                        $allowedSortFields = ['desc','asc'];
                        $allowedFilterFields = $model->getFillable();
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

                        foreach ($collectionSort->values()->toArray() as $k => $item) {
                            if (!empty($collectionFranchise)){
                                foreach ($collectionFranchise as $col){
                                    foreach ($item['categories'] as $key => $cat){
                                        if ($cat['franchise_id'] == $col['id'] ){
                                            $collectionResponse['data'][$k]['franchise'][$key]['label'] = $col['label'];
                                            $collectionResponse['data'][$k]['franchise'][$key]['value'] = $col['value'];
                                        }
                                    }
                                }
                            }
                            $collectionResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                            $collectionResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                            $collectionResponse['data'][$k]['title'] = $item['title'] ?? '';
                            $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                            $collectionResponse['data'][$k]['type_film'] = $item['type_film'] ?? '';
                            $collectionResponse['data'][$k]['poster'] = $item['poster']['src'] ?? '';
                        }
                        $collectionResponse['title'] = $collectionTitle;
                        $collectionResponse['total'] = $collapsed->count();
                        $collectionResponse['franchise'] = $collectionFranchise;

                        return $collectionResponse;
                    }
                }
            }
        }
        return [];
    }

    public function destroy(Request $request)
    {
        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
            'value' => 'required|string|max:50',
        ])->safe()->all();
        if (!empty($data)){
            $collectionArr = Collection::where('value',$data['value'])->get('id')->toArray();
        CollectionsCategoriesPivot::where([
            ['id_movie', '=', $data['id_movie']],
            ['collection_id', '=', $collectionArr[0]['id']],
        ])->delete();
        }
    }
}
