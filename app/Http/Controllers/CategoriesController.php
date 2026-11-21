<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Models\MovieCategory;
use App\Models\MovieInfo;
use App\Models\TagsMoviesPivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use function Nette\Utils\data;

class CategoriesController extends Controller
{

    public function index() : string
    {
        $collectionArray = Category::with((array('children' => function($query)  {
            $query->with('children');
        })))->get()->toArray();
        $collectionArray = cascaderStructure($collectionArray);
        return json_encode($collectionArray);
    }
    public function getLocalizing() : array
    {
        return [
          'collection'=> LanguageController::localizingCollectionInfo(),
          'franchise'=> LanguageController::localizingFranchiseInfo(),
        ];
    }
    public function getSections() : array
    {
        $sections = Category::get(['id','index_id','value','title_ru','title_en'])->toArray();
        return $sections?? [] ;
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
        $rawLabel = $request->input('label_en', '');
        $lowered = mb_strtolower($rawLabel, 'UTF-8');

        $withUnderscores = str_replace(' ', '_', $lowered);
        $slug = preg_replace('/[^a-z0-9_]+/', '', $withUnderscores);

        if (empty($slug)) {
            $slug = 'franchise_' . time();
        }

        $request->merge(['value' => $slug]);

        $validator = Validator::make($request->all(), [
            'label_en'   => 'required|string|max:50',
            'label_ru'   => 'required|string|max:50',
            'value'      => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'collection' => 'required|array',
            'collection.*' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $localModel = new LocalizingFranchise();
        $modelExist = $localModel->where('value', $request->input('value'))->first();

        if (!$modelExist) {
            DB::transaction(function () use ($request, $localModel) {
                $newFranchiseId = $localModel->insertGetId([
                    'value'    => $request->input('value'),
                    'label_en' => $request->input('label_en'),
                    'label_ru' => $request->input('label_ru'),
                ]);

                $dataTable = [];
                foreach ($request->input('collection') as $collectionId) {
                    $dataTable[] = [
                        'id' => $newFranchiseId,
                        'collection_id' => $collectionId,
                    ];
                }

                if (!empty($dataTable)) {
                    CollectionsFranchisesPivot::insert($dataTable);
                }
            });

            return response()->json(['message' => 'Success', 'id' => $newFranchiseId ?? null]);
        }

        return response()->json(['message' => 'Already exists'], 409);
    }
    public function addCollection(Request $request)
    {
        request()->merge([ 'value' => strtolower(str_ireplace(' ', '_',$request->label_en))]);
        $data = Validator::make($request->all(),[
            'label_en' => 'required|string|max:50',
            'label_ru' => 'required|string|max:50',
            'value' => 'required|string|max:50',
            'category_id' => 'required|numeric',
        ]);
        if ($data->fails()) {
            return response()->json([
                'errors' => $data->errors()
            ]);
        }
        $modelCollection = Collection::query();
        $modelExist = $modelCollection->where('value',$data->getValue('value'))->first();
        if (!$modelExist){
            $modelCollection->insert($data->getData());
        }
    }
    public function store(Request $request): array
    {
        $locale = Lang::locale();
        $data = Validator::make($request->all(), [
            'id_movie'   => 'required|string|max:10',
            'categories' => 'present|array',
            'tags'       => 'array',
            'viewed'     => 'boolean',
            'short'      => 'boolean',
            'adult'      => 'boolean',
        ])->safe()->all();
        $typeFilm = MovieInfo::where('id_movie', $data['id_movie'])->value('type_film');

        if ($typeFilm === null) {
            return ['success' => false, 'error' => 'Movie not found in MovieInfo'];
        }

        $data['type_film'] = $typeFilm;

        MovieInfo::where('id_movie', $data['id_movie'])
            ->where('published', 2)
            ->update(['published' => 1]);

        if (!empty($data['categories'])) {
            if (count($data['categories']) >= 2) {
                foreach ($data['categories'] as $arrLeft) {
                    if (array_search(90000, $arrLeft) === 0 || array_search(1000000, $arrLeft) === 0) {
                        if ($arrLeft[0] != 90000 && $arrLeft[0] != 1000000) {
                            return ['success' => false];
                        }
                        foreach ($data['categories'] as $arrRight) {
                            if ($arrRight[0] != $arrLeft[0]) {
                                return ['success' => false];
                            }
                        }
                    }
                }
            }

            $model = modelByName('MovieInfo');
            $typeCollection = $model->where('id_movie', '=', $data['id_movie'])->get('type_film');
            if (!$typeCollection->isEmpty()) {
                $data['type_film'] = $typeCollection[0]['type_film'];
            }
        }

        DB::transaction(function () use ($data, $locale) {

            MovieInfo::where('id_movie', $data['id_movie'])->lockForUpdate()->first();
            CollectionsCategoriesPivot::where('id_movie', $data['id_movie'])->delete();
            TagsMoviesPivot::where('id_movie', $data['id_movie'])->delete();
            MovieCategory::where('id_movie', $data['id_movie'])->delete();

            if (!empty($data['categories'])) {
                $groupedCats = [];

                foreach ($data['categories'] as $cat) {
                    $categoryId   = $cat[0];
                    $collectionId = $cat[1];
                    $franchiseId  = 0;

                    if (!empty($cat[2])) {
                        $franchiseId = (int) str_replace("fr_" . $cat[1], '', $cat[2]);
                    }

                    $groupKey = $collectionId;

                    if (!isset($groupedCats[$groupKey])) {
                        $groupedCats[$groupKey] = [
                            'category_id'  => $categoryId,
                            'franchise_id' => $franchiseId,
                        ];
                    } else {
                        if ($groupedCats[$groupKey]['franchise_id'] === 0 && $franchiseId > 0) {
                            $groupedCats[$groupKey]['franchise_id'] = $franchiseId;
                        }
                    }
                }

                $pivotData       = [];
                $movieCategories = [];

                foreach ($groupedCats as $collectionId => $catData) {
                    $movieCategories[] = [
                        'id_movie'    => $data['id_movie'],
                        'id_category' => $catData['category_id'],
                    ];

                    $pivotData[] = [
                        'id_movie'      => $data['id_movie'],
                        'collection_id' => $collectionId,
                        'franchise_id'  => $catData['franchise_id'],
                        'type_film'     => $data['type_film'],
                        'viewed'        => $data['viewed'] ?? 0,
                        'short'         => $data['short'] ?? 0,
                        'adult'         => $data['adult'] ?? 0,
                    ];
                }

                if (!empty($movieCategories)) {
                    MovieCategory::upsert($movieCategories, ['id_movie', 'id_category']);
                }

                if (!empty($pivotData)) {
                    CollectionsCategoriesPivot::insert($pivotData);
                }
            }

            if (!empty($data['tags'])) {
                $tagsToInsert = [];
                foreach ($data['tags'] as $tag) {
                    $tagExist = DB::table('tags')->where('tag_name_' . $locale, '=', $tag)->first();
                    if ($tagExist) {
                        $tagsToInsert[] = [
                            'id_movie'  => $data['id_movie'],
                            'id_tag'    => $tagExist->id,
                            'type_film' => $data['type_film'],
                        ];
                    }
                }
                if (!empty($tagsToInsert)) {
                    TagsMoviesPivot::insert($tagsToInsert);
                }
            }
        });

        return ['success' => true];
    }
}
