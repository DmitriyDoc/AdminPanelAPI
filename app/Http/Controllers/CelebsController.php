<?php

namespace App\Http\Controllers;

use App\Models\MovieInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class CelebsController extends Controller
{
    private const MEDIUM_SIZE = 'w300';
    private const SMALL_SIZE = 'w185';
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        $model = modelByName('CelebsInfo'.ucfirst(Lang::locale()));

        $allowedSortFields = ['desc','asc'];
        $allowedFilterFields = $model->getFillable();
        $limit = $request->query('limit',50);
        $sortDir = strtolower($request->query('spin','desc'));
        $sortBy = $request->query('orderBy','id');

        if (!in_array($sortBy,$allowedFilterFields)){
            $sortBy = $allowedFilterFields[0];
        }

        if (!in_array($sortDir,$allowedSortFields)){
            $sortDir = $allowedSortFields[0];
        }

        if ($request->query('search')){
            $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
            $model = $model->where('nameActor','like',"{$searchQuery}%")->orWhere('id_celeb','like',"{$searchQuery}%");
        }

        $modelArr = $model->select(['id_celeb', 'nameActor'])->with('info')->orderBy($sortBy,$sortDir)->paginate($limit)->toArray();

        if (!empty($modelArr['data'])){
            foreach ($modelArr['data'] as $k => $item) {
                if (!empty($item['info'])){
                    $modelArr['data'][$k]['created_at'] = !empty($item['info']['created_at']) ? date('Y-m-d', strtotime($item['info']['created_at'])) : '';

                    $photo = $item['info']['photo'] ?? '';
                    if (!empty($photo)) {
                        if (str_starts_with($photo, env('TMDB_IMAGE_BASE_URL').'/t/p/')) {
                            $search = ['/t/p/original/', '/t/p/w500/', '/t/p/w780/', '/t/p/w1280/'];
                            $replace = '/t/p/' . self::MEDIUM_SIZE . '/';
                            $modelArr['data'][$k]['poster'] = str_replace($search, $replace, $photo);
                        } else {
                            $modelArr['data'][$k]['poster'] = $photo;
                        }
                    } else {
                        $modelArr['data'][$k]['poster'] = '';
                    }
                }
            }
        }

        $modelArr['locale'] = LanguageController::localizingPersonsList();
        return $modelArr;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $knownFor = [];
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
        $curLocale = app()->currentLocale();

        if (!empty($id)) {
            $model = modelByName('CelebsInfo'.ucfirst(Lang::locale()));
            $modelRecord = $model->where('id_celeb',$id)->with('info')->first();

            if (!$modelRecord) {
                return [];
            }

            $modelArr = $modelRecord->toArray();

            $photo = $modelArr['info']['photo'] ?? '';
            if (!empty($photo)) {
                if (str_starts_with($photo, env('TMDB_IMAGE_BASE_URL').'/t/p/')) {
                    $search = ['/t/p/original/', '/t/p/w500/', '/t/p/w780/', '/t/p/w1280/'];
                    $replace = '/t/p/' . self::MEDIUM_SIZE . '/';
                    $modelArr['info']['photo'] = str_replace($search, $replace, $photo);
                } else {
                    $modelArr['info']['photo'] = $photo; // Оставляем как есть
                }
            } else {
                $modelArr['info']['photo'] = '';
            }

            if (!empty($modelArr['info']['knowfor'])){
                $knownForArr = json_decode($modelArr['info']['knowfor'], true) ?? [];

                foreach ($allowedTableNames as $tableName){
                    $relationPosterName = 'poster'.$tableName;
                    $relationPosterNameSnake = 'poster_'.camelToSnake($tableName);

                    foreach ($knownForArr as $index => $movieId) {
                        $res = MovieInfo::with($relationPosterName)
                            ->where('type_film', getTableSegmentOrTypeId($tableName))
                            ->where('id_movie', $movieId)
                            ->first();

                        if (!empty($res)){
                            $resArr = $res->toArray();
                            $currentType = getTableSegmentOrTypeId($resArr['type_film']);

                            $knownFor[$index]['id_movie'] = $resArr['id_movie'];
                            $knownFor[$index]['type_film'] = __('movies.type_movies.'.$currentType);
                            $knownFor[$index]['type_film_slug'] = $currentType;
                            $knownFor[$index]['title'] = $resArr['title'];
                            $knownFor[$index]['original_title'] = $resArr['original_title'];
                            $knownFor[$index]['poster'] = '';

                            if (!empty($resArr[$relationPosterNameSnake])){
                                $posterSrc = $resArr[$relationPosterNameSnake][0]['src'] ?? '';

                                if (!empty($posterSrc)) {
                                    // 👇 ТА ЖЕ ЛОГИКА: проверяем TMDB и оптимизируем, иначе оставляем как есть
                                    if (str_starts_with($posterSrc, env('TMDB_IMAGE_BASE_URL').'/t/p/')) {
                                        $search = ['/t/p/original/', '/t/p/w500/', '/t/p/w780/', '/t/p/w1280/'];
                                        $replace = '/t/p/' . self::SMALL_SIZE . '/';
                                        $knownFor[$index]['poster'] = str_replace($search, $replace, $posterSrc);
                                    } else {
                                        $knownFor[$index]['poster'] = $posterSrc; // Оставляем как есть
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $modelArr['info']['knowfor'] = array_values($knownFor);
            $modelArr['filmography'] = json_decode($modelArr['filmography'], true) ?? [];

            $allMovieIds = [];
            foreach ($modelArr['filmography'] as $occupation) {
                foreach ($occupation as $imdbId => $dataArr) {
                    $allMovieIds[] = $imdbId;
                }
            }
            $allMovieIds = array_unique($allMovieIds);

            $moviesInfoCollection = MovieInfo::whereIn('id_movie', $allMovieIds)
                ->select('id_movie', 'published', 'type_film')
                ->get()
                ->keyBy('id_movie');

            $filmographyArr = [];
            foreach ($modelArr['filmography'] as $k => &$occupation) {
                uasort($occupation, function ($a, $b) {
                    return ($a['year'] < $b['year']) ? 1 : -1;
                });

                foreach ($occupation as $id => $dataArr) {
                    $movieInfo = $moviesInfoCollection->get($id);
                    $statusVal = $movieInfo ? $movieInfo->published : null;
                    $typeFilmVal = $movieInfo ? getTableSegmentOrTypeId($movieInfo->type_film) : null;
                    $categoryName = $movieInfo ? getCategoryNameByTableSegment($typeFilmVal, $curLocale) : null;

                    $filmographyArr[$k][] = [
                        'id' => $id,
                        'role' => $dataArr['role'],
                        'year' => $dataArr['year'],
                        'title' => $dataArr['title'],
                        'type_film' => $typeFilmVal,
                        'cat_name' => $categoryName,
                        'status' => statusSelection($statusVal) ?? [],
                    ];
                }
            }

            $modelArr['filmography'] = $filmographyArr;
            $modelArr['info']['created_at'] = !empty($modelArr['info']['created_at']) ? date('Y-m-d', strtotime($modelArr['info']['created_at'])) : '';
            $modelArr['info']['updated_at'] = !empty($modelArr['info']['updated_at']) ? date('Y-m-d', strtotime($modelArr['info']['updated_at'])) : '';
            $modelArr['locale'] = LanguageController::localizingPersonShow();

            return $modelArr;
        }

        return [];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }
    public function removeFromFilmography(Request $request){
        $dataRequest = $request->all();
        $match = preg_match("/nm\d{1,10}/",$dataRequest['data']['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['data']['id'] : null;
        $itemsArr = ($dataRequest['data']['id_items']) && is_array($dataRequest['data']['id_items']) ? $dataRequest['data']['id_items'] : [];
        $tabIndex = $dataRequest['data']['tab_index'];

        if (!empty($id) && !empty($itemsArr)){
            transaction( function () use ($tabIndex,$itemsArr,$id){
                $celebsModelNames = [
                    0 => 'CelebsInfoEn',
                    1 => 'CelebsInfoRu'
                ];
                foreach ($celebsModelNames as $name){
                   $model = modelByName($name)->where('id_celeb',$id);
                   $collection =  $model->get(['filmography','id']);
                   if ($collection->isNotEmpty()){
                       $filmographyDecodeArr = json_decode($collection->first()->filmography,true);
                       foreach ($filmographyDecodeArr as $occupIndex => $occupation){
                           foreach ($occupation as $index => $key){
                               foreach ($itemsArr as $delId){
                                   if ($index == $delId && $occupIndex == $tabIndex) {
                                       unset($filmographyDecodeArr[$occupIndex][$index]);
                                   }
                               }
                           }

                       }
                       $updateModel = $model->find($collection->first()->id);
                       $updateModel->filmography = json_encode($filmographyDecodeArr);
                       $updateModel->save();
                   }
                }
            });
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $types = [
            0=>'CelebsInfo',
            1=>'CelebsInfoEn',
            2=>'CelebsInfoRu',
            3=>'ImagesCelebs',
        ];
        $dataRequest = $request->all();
        $match = preg_match("/nm\d{1,10}/",$dataRequest['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['id'] : null;
        if (!empty($id)) {
            $modelInfo = convertVariableToModelName('CelebsInfo','', ['App', 'Models']);
            $modelExist = $modelInfo::where('id_celeb',$id)->first();
            if ($modelExist){
                transaction( function () use ($id,$types){
                    foreach ($types as $type){
                        $model = convertVariableToModelName($type,'', ['App', 'Models']);
                        $model::where('id_celeb',$id)->delete();
                    }
                });
            }
        }
    }
}
