<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\Franchise;
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
            $franchiseArr = Franchise::where('value',$slugFran)->get(['id','label'])->toArray();
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
                    $collection->add($model->select('type_film','id_movie','title','created_at','updated_at')->whereIn('id_movie',$item)->with('poster')->get()->all());
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
                    foreach ($collectionSort->values()->toArray() as $k => $item) {
                        $collectionResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                        $collectionResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                        $collectionResponse['data'][$k]['title'] = $item['title'] ?? '';
                        $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                        $collectionResponse['data'][$k]['type_film'] = $item['type_film'] ?? '';
                        $collectionResponse['data'][$k]['poster'] = $item['poster']['src'] ?? '';
                    }
                    $collectionResponse['title'] = $collectionTitle;
                    $collectionResponse['total'] = $collapsed->count();

                    return $collectionResponse;
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
            $collectionArr = Franchise::where('value',$data['value'])->get('id')->toArray();
        CollectionsCategoriesPivot::where([
            ['id_movie', '=', $data['id_movie']],
            ['franchise_id', '=', $collectionArr[0]['id']],
        ])->delete();
        }
    }
}
