<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IdTypeTvMovie extends Model
{
    public $segment = 'tv_movie';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movies_id_type_tv_movie';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'id_movie',
        'title',
        'created_at',
        'updated_at',
    ];


    public function poster()
    {
        return $this->hasOne(PostersTvMovie::class,'id_movie','id_movie')->oldest();
    }
    public function getCountAttribute(){
        return $this->count();
    }
    public function getLastDayCountAttribute(){

        return $this->where('created_at','>=',Carbon::now()->subdays(1))->count();
    }
    public function info()
    {
        return $this->hasOne(InfoTvMovie::class,'id_movie','id_movie');
    }
    public function categories()
    {
        return $this->hasOne(CollectionsCategoriesPivot::class,'id_movie','id_movie');
    }
}
