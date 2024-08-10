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
            'collection_id' => 'required|numeric',
        ]);
        if ($data->fails()) {
            return response()->json([
                'errors' => $data->errors()
            ], 422);
        }

        $localModel = LocalizingFranchise::firstOrCreate([
                'label' => $data->getValue('label'),
                'value' => $data->getValue('value'),
                'label_ru' => $data->getValue('label_ru')
            ]);

        CollectionsFranchisesPivot::updateOrCreate([
            'id' => $localModel->id,
            'collection_id' => $data->getValue('collection_id'),
        ]);

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
    public function store(Request $request)
    {

        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
            'type_film' => 'required|string|max:12',
            'collection_id' => 'required|numeric',
            'franchise_id' => 'string|max:12',
            'viewed' => 'boolean',
            'short' => 'boolean',
        ])->safe()->all();
        if (!empty($data['franchise_id'])){
            $data['franchise_id'] = str_replace("fr_".$data['collection_id'], '', $data['franchise_id']);;
        }
        transaction( function () use ($data){
            CollectionsCategoriesPivot::where('id_movie',$data['id_movie'])->delete();
            CollectionsCategoriesPivot::create($data);
        });
    }
}
