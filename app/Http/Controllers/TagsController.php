<?php
namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagsController
{
    public function index(Request $request,$tagName):array
    {
        $tagData = Tag::where('value',$tagName)->with('children')->get()->toArray();

        if (!empty($tagData[0]['children'])){
            $tagTitle = $tagData[0]['tag_name'];
            $TypeFilmArray = [];
            $collection = collect();
            $tagResponse = [];
            array_walk($tagData[0]['children'], function($item, $key) use (&$TypeFilmArray) {
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
                    if (!empty($collapsedPosters)){
                        foreach ($collapsedPosters as $posterItem){
                            if ($item['id_movie'] == $posterItem['id_movie']){
                                $img = explode(',',$posterItem['srcset'] ?? '');
                                $tagResponse['data'][$k]['poster'] = $img[0] ?? '';
                            }
                        }
                    }
                    $tagResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                    $tagResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                    $tagResponse['data'][$k]['title'] = $item['title'] ?? '';
                    $tagResponse['data'][$k]['year'] = $item['year_release'] ?? null;
                    $tagResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                    $tagResponse['data'][$k]['type_film'] = getTableSegmentOrTypeId($item['type_film']) ?? '';
                }
                $tagResponse['title'] = $tagTitle;
                $tagResponse['total'] = $collapsed->count();
                return $tagResponse;
            }
        }
        return [];
    }
    public function list(Request $request):array
    {
        $model = convertVariableToModelName('Tag','', ['App', 'Models']);
        $CollectionTag =  $model->all();

        if ($CollectionTag->isNotEmpty()){
            $allowedFilterFields = $model->getFillable();
            if ($request->has('search')){
                $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
                if (!empty($searchQuery)){
                    $CollectionTag = $CollectionTag->where('tag_name',$searchQuery);
                }
            }
            $itemsCount = $CollectionTag->count()??0;
            $allowedSortFields = ['desc','asc'];
            $limit = $request->query('limit',20);
            $page = $request->query('page',1);
            $sortDir = strtolower($request->query('spin','asc'));
            $sortBy = $request->query('orderBy','created_at');
            if (!in_array($sortBy,$allowedFilterFields)){
                $sortBy = $allowedSortFields[0];
            }
            if (in_array($sortDir,$allowedSortFields)){
                $sortDir == 'desc' ? $CollectionTag = $CollectionTag->sortByDesc($sortBy) : $CollectionTag->sortBy($sortBy);
            }
            $collectionSort = $CollectionTag->forPage($page,$limit);
            $collectionSortArr = $collectionSort->values()->toArray();
            return [
                'total' => $itemsCount,
                'data' => $collectionSortArr
            ];
        }
        return [];
    }
}
