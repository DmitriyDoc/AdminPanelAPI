<?php

namespace App\Traits;

use App\Models\IdTypeMovies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ParserTrait
{
    public function insertDB($table,$data){
        DB::table($table)->insertOrIgnore($data);
        gc_collect_cycles(); // принудительный вызов встроенного сборщика мусора PHP
    }
    public function updateOrInsert($table,$data,$signKey){
        DB::table($table)
            ->updateOrInsert(
                [$signKey => $data[$signKey]],
                $data??[]
            );
        gc_collect_cycles(); // принудительный вызов встроенного сборщика мусора PHP
    }

    public function touchDB($model,$id,$key){
        $model->where($key,'=',$id)->touch();
    }

    public function deleteById($table,$signField,$id){
        DB::table($table)->where($signField, '=', $id)->delete();
        gc_collect_cycles(); // принудительный вызов встроенного сборщика мусора PHP
    }

    public function logErrors($document,$exspression,$marker,$url=null){
        if ($document->has($exspression)){
            Log::info(date('Y-m-d H:i:s').$marker.$url.PHP_EOL);
        }
    }
}
