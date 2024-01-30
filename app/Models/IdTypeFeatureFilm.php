<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IdTypeFeatureFilm extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movies_id_type_feature_film';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_movie',
        'title',
        'created_at',
        'updated_at',
    ];
    public function poster()
    {
        return $this->hasOne(PostersFeatureFilm::class,'id_movie','id_movie')->oldest();
    }
    public function getCountAttribute(){
        return $this->count();
    }
    public function getLastDayCountAttribute(){

        return $this->where('created_at','>=',Carbon::now()->subdays(1))->count();
    }
    public function info()
    {
        return $this->hasOne(InfoFeatureFilm::class,'id_movie','id_movie');
    }


}
