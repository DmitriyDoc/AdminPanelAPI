<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Models\TagsMoviesPivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
        ];
        $data = Validator::make($request->all(),[
            'id_movie' => 'required|string|max:10',
            'type_film' => 'required|string|max:12',
            'categories' => 'present|array',
            'tags' => 'array',
            'viewed' => 'boolean',
            'short' => 'boolean',
            'adult' => 'boolean',
        ])->safe()->all();
        if (!empty($data['categories'])){
            if (count($data['categories']) >= 2) {
                foreach ($data['categories'] as $arrLeft){
                    if (array_search(90000,$arrLeft)  === 0 || array_search(1000000 ,$arrLeft) === 0){
                        if ($arrLeft[0] != 90000 && $arrLeft[0] != 1000000){
                            return ['success'=>false];
                        }
                        foreach ($data['categories'] as $arrRight){
                            if ($arrRight[0] != $arrLeft[0]){
                                return ['success'=>false];
                            }
                        }
                    }
                }
            }
            $model = modelByName('MovieInfo');
            //foreach ($allowedTableNames as $tableName){
                //$model = convertVariableToModelName('IdType',$tableName, ['App', 'Models']);
                $typeCollection = $model->where('id_movie','=',$data['id_movie'])->get('type_film');
                if (!$typeCollection->isEmpty()){
                    $data['type_film'] = $typeCollection[0]['type_film'];
                    //break;
                }
            //}
        }
        transaction( function () use ($data){
            CollectionsCategoriesPivot::where('id_movie',$data['id_movie'])->delete();
            TagsMoviesPivot::where('id_movie',$data['id_movie'])->delete();
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
                if (!empty($data['tags'])){
                    foreach ($data['tags'] as $tag){
                        $tagExist = DB::table('tags')->where('tag_name','=',$tag)->first();
                        if ($tagExist){
                            TagsMoviesPivot::create([
                                'id_movie' => $data['id_movie'],
                                'id_tag' => $tagExist->id,
                                'type_film' => $data['type_film']
                            ]);
                        }
                    }
                }
            }
        });
        return ['success'=>true];
    }
}
