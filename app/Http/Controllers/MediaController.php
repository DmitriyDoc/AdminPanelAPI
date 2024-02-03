<?php

namespace App\Http\Controllers;

use App\Models\InfoCelebs;
use Illuminate\Http\Request;

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
            8=>'Celebs',
        ];
//        if (!in_array($slug,$allowedTableNames)){
//            $slug = $allowedTableNames[0];
//        }

        if (!in_array($imgType,$allowedTypesNames)){
            $imgType = 'images';
        }

        if (!in_array($slug,$allowedTableNames)){
            $slug = 'all';
        }

        if (!empty(strip_tags($id))){
            if ($slug == 'all') {
                foreach ($allowedTableNames as $type){
                    $model = convertVariableToModelName(ucfirst($imgType), $type, ['App', 'Models']);
                    $res = $model::select('id','id_movie','src','srcset','namesCelebsImg')->where('id_movie',$id)->simplePaginate(10)->toArray();
                    if (!empty($res['data'])) break;
                }
            } elseif ($slug == 'Celebs')  {
                $model = convertVariableToModelName(ucfirst($imgType),$slug, ['App', 'Models']);
                $res = $model::select('id','id_celeb','src','srcset','namesCelebsImg')->where('id_celeb',$id)->simplePaginate(10)->toArray();
            } else {
                $model = convertVariableToModelName(ucfirst($imgType),$slug, ['App', 'Models']);
                $res = $model::select('id','id_movie','src','srcset','namesCelebsImg')->where('id_movie',$id)->simplePaginate(10)->toArray();
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
            $model::destroy($request->all());
            return [
                'success' => true,
                'status' => 200,
            ];

        }

    }
}
