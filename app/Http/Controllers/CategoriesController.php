<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CollectionsCategoriesPivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function Nette\Utils\data;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index():string
    {
        $collectionArray = Category::with('children')->get()->toArray();
        if ( !empty($collectionArray) ){
            foreach ( $collectionArray as $catKey => $catValue ){
                $collectionArray[$catKey]['label'] = $catValue['label'];
                $collectionArray[$catKey]['value'] = $catValue['id'];
                unset($collectionArray[$catKey]['id']);
                unset($collectionArray[$catKey]['title']);
                foreach ( $catValue['children'] as $colKey => $colValue ){
                    $collectionArray[$catKey]['children'][$colKey]['label'] = $colValue['label'];
                    $collectionArray[$catKey]['children'][$colKey]['value'] = $colValue['id'];
                    unset($collectionArray[$catKey]['children'][$colKey]['id']);
                    unset($collectionArray[$catKey]['children'][$colKey]['category_id']);
                }
            }
            $collectionJson = json_encode($collectionArray);
        }
        return $collectionJson??'';
    }

    public function store(Request $request)
    {
        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
            'type_film' => 'required|string|max:12',
            'collection_id' => 'required|numeric',
            'viewed' => 'boolean',
        ])->safe()->all();
        transaction( function () use ($data){
            CollectionsCategoriesPivot::where('id_movie',$data['id_movie'])->delete();
            CollectionsCategoriesPivot::create($data);
        });


    }
}
