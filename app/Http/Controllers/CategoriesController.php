<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\Franchise;
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
        //dd($collectionArray);
        $collectionArray = cascaderStructure($collectionArray);
        return json_encode($collectionArray);
    }

    public function getSections():string
    {
        $sections = Category::get()->toArray();
        return json_encode($sections ?? '');
    }

    public function show()
    {
        $collectionArray = Category::with('children')->get()->toArray();
        $collectionArray = cascaderStructure($collectionArray);
        return json_encode($collectionArray);
    }
    public function add(Request $request)
    {
        request()->merge([ 'value' => strtolower(str_ireplace(' ', '_',$request->label)) ]);
        $data = Validator::make($request->all(),[
            'label' => 'required|string|max:50',
            'value' => 'required|string|max:50',
            'collection_id' => 'required|numeric',
        ]);
        if ($data->fails()) {
            return response()->json([
                'errors' => $data->errors()
            ], 422);
        }
        Franchise::create($data->getData());
    }
    public function store(Request $request)
    {
        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
            'type_film' => 'required|string|max:12',
            'collection_id' => 'required|numeric',
            'franchise_id' => 'numeric',
            'viewed' => 'boolean',
            'short' => 'boolean',
        ])->safe()->all();
        transaction( function () use ($data){
            CollectionsCategoriesPivot::where('id_movie',$data['id_movie'])->delete();
            CollectionsCategoriesPivot::create($data);
        });
    }
}
