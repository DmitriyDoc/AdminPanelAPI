<?php
namespace App\Http\Controllers;


use App\Models\InfoFeatureFilm;
use App\Models\InfoMiniSeries;
use App\Models\InfoShortFilm;
use App\Models\InfoTvSeries;
use App\Models\InfoTvShort;
use App\Models\InfoTvSpecial;
use App\Models\InfoVideo;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagsController
{
    public function testTags()
    {
//        $pivot = DB::table('collections_categories_pivot_test')->get();
//        foreach ( $pivot->toArray() as $item){
//            $model = convertVariableToModelName('Info',$item->type_film, ['App', 'Models']);
//            DB::table('collections_categories_pivot_test')->where('id_movie','=',$item->id_movie)->update(
//                ['type_film_int' => getTableSegmentOrTypeId($item->type_film)]
//            );
//
//            $collection = $model::whereNotNull('genres')->where('id_movie','=',$item->id_movie)->get(['genres']);
//            if ($collection->isNotEmpty()){
//                $unserialize = unserialize($collection[0]['genres']);
//                foreach ($unserialize['genres'] as $tag) {
//                    $tagExist = DB::table('tags')->where('tag_name','=',$tag)->first();
//                    if (!$tagExist){
//                        $translator = new TranslatorController();
//                        $tagRus = $translator->translateTag($tag) ?? null;
//                        DB::table('tags')->updateOrInsert(
//                            ['tag_name' => $tag],
//                            ['tag_name_ru' => $tagRus]
//                        );
//                    }
//                    if ($tagExist){
//                        DB::table('tags_movies_pivot')->insertOrIgnore([
//                            ['id_movie' => $item->id_movie,'id_tag' => $tagExist->id,'id_type_movie' => $item->type_film_int],
//                        ]);
//                    }
//                }
//            }
//        }
    }
}
