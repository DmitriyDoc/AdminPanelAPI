<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\InfoCelebs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index( string $imgType, string $slug, string $id )
    {
        $allowedTypesNames = [
            0=>'images',
            1=>'posters',
        ];
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


        if (!in_array($imgType,$allowedTypesNames)){
            $imgType = 'images';
        }

        if (!empty(strip_tags($id))){
            if ($slug == 'Celebs') {
                $model = convertVariableToModelName(ucfirst($imgType),$slug, ['App', 'Models']);
                $res = $model::select('id','id_celeb','src','srcset','namesCelebsImg')->where('id_celeb',$id)->simplePaginate(10)->toArray();
            } elseif (in_array($slug,$allowedTableNames))  {

                $model = convertVariableToModelName(ucfirst($imgType),$slug, ['App', 'Models']);
                $model = $model::select('id','id_movie','src','srcset','namesCelebsImg')->where('id_movie',$id);
                if ($model->get()->isEmpty()){
                    foreach ($allowedTableNames as $type){
                        $model = convertVariableToModelName(ucfirst($imgType),$type, ['App', 'Models']);
                        $model = $model::select('id','id_movie','src','srcset','namesCelebsImg')->where('id_movie',$id);
                        if ($model->get()->isNotEmpty()) break;
                    }
                }

                if ($imgType == 'posters' && $model->get()->isNotEmpty()){
                    $model->with('assignPosters');
                }
                $res = $model->simplePaginate(10)->toArray();
            }
            if (!empty($res)){
                foreach ($res['data'] as &$item){
                    $imagesArr = explode(',',$item['srcset'] ?? '');
                    $sortImgArr = [];
                    foreach ($imagesArr as $key => $img){
                        $resArr =  explode(' ',$img);
                        $resArr = array_reverse($resArr);
                        $sortImgArr[$resArr[0]] = $resArr[1]??'';
                    }
                    ksort($sortImgArr,SORT_NATURAL );
                    $item['srcset'] = $sortImgArr[array_key_first($sortImgArr)];
                    $item['src'] = $sortImgArr['1024w'] ?? $sortImgArr[array_key_last($sortImgArr)];
                    if ($imgType == 'posters'){
                        $item['status_poster'] = $this->checkAssignPoster($item['id'],$item['assign_posters']);
                    }
                }
            }
            $modelAssignPoster = AssignPoster::where('id_movie',$id)->get();
            if ($modelAssignPoster->isNotEmpty()){
                $arrayAssignPoster = $modelAssignPoster->toArray();
                $res['poster_count']['id_poster_original'] = $arrayAssignPoster[0]['id_poster_original'] ? 1 : 0;
                $res['poster_count']['id_poster_ru'] = $arrayAssignPoster[0]['id_poster_ru'] ? 1 : 0;
                $res['poster_count']['id_posters_characters'] = count(json_decode($arrayAssignPoster[0]['id_posters_characters']??'')??[]) ?? 0;
                $res['poster_count']['id_posters_alternative'] = count(json_decode($arrayAssignPoster[0]['id_posters_alternative']??'')??[]) ?? 0;
                $res['poster_count']['id_wallpaper'] = $arrayAssignPoster[0]['id_wallpaper'] ? 1 : 0;
            }
            return $res ?? [];
        }

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
        $data = $request->all();
        $allowedPosterAssignNames = [
            0=>'id_poster_original',
            1=>'id_poster_ru',
            2=>'id_posters_characters',
            3=>'id_posters_alternative',
            4=>'id_wallpaper',
        ];
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
        if (in_array($data['type_film'],$allowedTableNames) && in_array($data['poster_cat'],$allowedPosterAssignNames) ){
            switch ($data['poster_cat']) {
                case 'id_poster_original':
                    $id = $data['id_poster'][0];
                    break;
                case 'id_poster_ru':
                    $id = $data['id_poster'][0];
                    break;
                case 'id_posters_characters':
                    $id = json_encode($data['id_poster']);
                    break;
                case 'id_posters_alternative':
                    $id = json_encode($data['id_poster']);
                    break;
                case 'id_wallpaper':
                    $id = $data['id_poster'][0];
                    break;
                default:
                    $id = null;
            }
            //$id = count($data['id_poster']) > 1 && ($data['poster_cat'] == 'id_posters_characters' || $data['poster_cat']  == 'id_posters_alternative') ? json_encode($data['id_poster']) : $data['id_poster'][0];
            $model = AssignPoster::where('id_movie',$data['id_movie'])->first();
            if ($model && !empty($id)){
                AssignPoster::where('id_movie',$data['id_movie'])->update([
                    $data['poster_cat']=>$id,
                    'type_film'=>getTableSegmentOrTypeId($data['type_film']),
                ]);
            } else {
                AssignPoster::create([
                    'id_movie'=>$data['id_movie'],
                    $data['poster_cat']=>$id,
                    'type_film'=>getTableSegmentOrTypeId($data['type_film']),
                ]);
            }
        }
    }


    public function showImages( string $slug, string $id)
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
        $res = [];
        if (in_array($slug,$allowedTableNames) && !empty(strip_tags($id))){
            $model = convertVariableToModelName('Images',$slug, ['App', 'Models']);
            $collection = $model::select('id','id_movie','src','srcset','namesCelebsImg')->where('id_movie',$id)->whereNotNull('src',)->get();
            if ($collection->isEmpty()){
                foreach ($allowedTableNames as $type){
                    $model = convertVariableToModelName('Images',$type, ['App', 'Models']);
                    $collection = $model::select('id','id_movie','src','srcset','namesCelebsImg')->where('id_movie',$id)->whereNotNull('src',)->get();
                    if (!$collection->isEmpty()) break;
                }
            }
            $res = $collection->shuffle()->take(50)->toArray();

            if (!empty($res)){
                foreach ($res as &$item){
                    $imagesArr = explode(',',$item['srcset'] ?? '');
                    $sortImgArr = [];
                    foreach ($imagesArr as $key => $img){
                        $resArr =  explode(' ',$img);
                        $resArr = array_reverse($resArr);
                        $sortImgArr[$resArr[0]] = $resArr[1]??'';
                    }
                    ksort($sortImgArr,SORT_NATURAL );
                    $item['srcset'] = $sortImgArr[array_key_first($sortImgArr)];
                    $item['src'] = $sortImgArr['1024w'] ?? $sortImgArr[array_key_last($sortImgArr)];
                }
            }
        }
        return $res;
    }

    public function showPosters( string $idMovie)
    {
        $res = [];
        if (!empty(strip_tags($idMovie))){
            $assignModel = AssignPoster::where('id_movie',$idMovie)->get();
            if ($assignModel->isNotEmpty()){
                $idArr = [];
                $assignPostersArr = $assignModel[0]->toArray();
                $type = $assignPostersArr['type_film'];
                unset($assignPostersArr['type_film']);
                unset($assignPostersArr['id_movie']);
                unset($assignPostersArr['id']);
                foreach ($assignPostersArr as $item){
                    if ($item){
                        if (is_string($item)){
                            foreach (json_decode($item) as $id){
                                array_push($idArr,$id);
                            }
                        } else {
                            array_push($idArr,$item);
                        }
                    }

                }
                unset($assignPostersArr);
                $model = convertVariableToModelName('Posters',getTableSegmentOrTypeId($type), ['App', 'Models']);
                $res = $model::select('id','id_movie','src','srcset')->whereIn('id',$idArr)->get()->toArray();

                if (!empty($res)){
                    foreach ($res as &$item){
                        $imagesArr = explode(',',$item['srcset'] ?? '');
                        $sortImgArr = [];
                        foreach ($imagesArr as $key => $img){
                            $resArr =  explode(' ',$img);
                            $resArr = array_reverse($resArr);
                            $sortImgArr[$resArr[0]] = $resArr[1]??'';
                        }
                        ksort($sortImgArr,SORT_NATURAL );
                        $item['srcset'] = $sortImgArr[array_key_first($sortImgArr)];
                        $item['src'] = $sortImgArr['1024w'] ?? $sortImgArr[array_key_last($sortImgArr)];
                    }
                }

            }
        }

        return $res;
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $type, string $slug,Request $request)
    {
        $allowedTypesNames = [
            0=>'images',
            1=>'posters',
        ];
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
            8=>'Celebs',
        ];

        if (in_array($type,$allowedTypesNames) && in_array($slug,$allowedTableNames)){
            $model = convertVariableToModelName(ucfirst($type),$slug, ['App', 'Models']);
            $model::whereIn('id', $request->all())->delete();
            return [
                'success' => true,
                'status' => 200,
            ];

        }

    }

    private function checkAssignPoster($id, $assignArray = null){
        if (!empty($assignArray)){
            foreach ($assignArray as $key => $item){
                switch ($key) {
                    case 'id_poster_original':
                        if ($item == $id){
                            return "Original poster";
                        }
                        break;
                    case 'id_poster_ru':
                        if ($item == $id){
                            return "Russian poster";
                        }
                        break;
                    case 'id_posters_characters':
                        if (!empty($item)){
                            $decodeArr = json_decode($item,true);
                            if (in_array($id, $decodeArr)){
                                unset($decodeArr);
                                return "Character poster";
                            }
                        }
                        break;
                    case 'id_posters_alternative':
                        if (!empty($item)){
                            $decodeArr = json_decode($item,true);
                            if (in_array($id, $decodeArr)){
                                unset($decodeArr);
                                return "Alternative poster";
                            }
                        }
                        break;
                    case 'id_wallpaper':
                        if ($item == $id){
                            return "Wallpaper";
                        }
                        break;
                }
            }
        }
        return null;
    }
}
