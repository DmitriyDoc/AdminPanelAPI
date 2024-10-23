<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function Nette\Utils\data;

class CategoriesController extends Controller
{

    public function index():string
    {
        $collectionArray = Category::with((array('children' => function($query)  {
            $query->with('children');
        })))->get()->toArray();
        $collectionArray = cascaderStructure($collectionArray);
        return json_encode($collectionArray);
    }

    public function getSections():string
    {
        $sections = Category::get()->toArray();
        return json_encode($sections ?? '');
    }

    public function showSelectFranchise()
    {
        $collectionArray = Category::with('children')->get()->toArray();
        $collectionArray = cascaderStructure($collectionArray);
        return json_encode($collectionArray);
    }
    public function showSelectCollection()
    {
        $collectionArray = Category::get()->toArray();
        return json_encode($collectionArray);
    }
    public function addFranchise(Request $request)
    {
        request()->merge([ 'value' => strtolower(str_ireplace(' ', '_',$request->label))]);
        $data = Validator::make($request->all(),[
            'label' => 'required|string|max:50',
            'value' => 'required|string|max:50',
            'label_ru' => 'required|string|max:50',
            "collection"    => "required|array",
            "collection.*"  => "required|numeric",
        ]);
        if ($data->fails()) {
            return response()->json([
                'errors' => $data->errors()
            ], 422);
        }

        transaction( function () use ($data){
            $localModel = LocalizingFranchise::firstOrCreate([
                'label' => $data->getValue('label'),
                'value' => $data->getValue('value'),
                'label_ru' => $data->getValue('label_ru')
            ]);
            foreach ($data->getValue('collection') as $collectionId){
                $dataTable[] = [
                    'id' => $localModel->id,
                    'collection_id' => $collectionId,
                ];
            }
            CollectionsFranchisesPivot::insert($dataTable);
        });
    }
    public function addCollection(Request $request)
    {
    request()->merge([ 'value' => strtolower(str_ireplace(' ', '_',$request->label))]);
    $data = Validator::make($request->all(),[
        'label' => 'required|string|max:50',
        'label_ru' => 'required|string|max:50',
        'value' => 'required|string|max:50',
        'category_id' => 'required|numeric',
    ]);
    if ($data->fails()) {
        return response()->json([
            'errors' => $data->errors()
        ], 422);
    }
    Collection::create($data->getData());
}
    public function store(Request $request):array
    {
        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
            'type_film' => 'required|string|max:12',
            'categories' => 'present|array',
            'viewed' => 'boolean',
            'short' => 'boolean',
            'adult' => 'boolean',
        ])->safe()->all();
        if (!empty($data['categories'])){

            foreach ($data['categories'] as $arr){
                if (array_search(90000,$arr)  === 0 || array_search(1000000 ,$arr) === 0){
                    if (count($data['categories']) > 1) {
                        return ['success'=>false];
                    }

                }
            }
        }
        transaction( function () use ($data){
            CollectionsCategoriesPivot::where('id_movie',$data['id_movie'])->delete();
            if (!empty($data['categories'])){
                foreach ( $data['categories'] as $cat){
                    $franchiseId = null;
                    if (!empty($cat[2])){
                        $franchiseId = str_replace("fr_".$cat[1], '', $cat[2]);
                    }
                    CollectionsCategoriesPivot::create([
                        'id_movie' =>$data['id_movie'],
                        'type_film' => $data['type_film'],
                        'collection_id' => $cat[1],
                        'franchise_id' => $franchiseId,
                        'viewed' => $data['viewed'] ?? null,
                        'short' => $data['short'] ?? null,
                        'adult' => $data['adult'] ?? null,
                    ]);
                }
            }
        });
        return ['success'=>true];
    }
}
