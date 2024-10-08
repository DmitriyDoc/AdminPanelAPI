<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IdTypeTvShort extends Model
{
    public $segment = 'tv_short';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movies_id_type_tv_short';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'id_movie',
        'title',
        'year',
        'created_at',
        'updated_at',
    ];
    public function assignPoster()
    {
        return $this->hasOne(AssignPoster::class,'id_movie','id_movie');
    }
    public function poster()
    {
        return $this->hasMany(PostersTvShort::class,'id_movie','id_movie')->oldest();
    }
    public function getCountAttribute(){
        return $this->count();
    }
    public function getLastDayCountAttribute(){

        return $this->where('created_at','>=',Carbon::now()->subdays(1))->count();
    }
    public function info()
    {
        return $this->hasOne(InfoTvShort::class,'id_movie','id_movie');
    }
    public function categories()
    {
        return $this->hasMany(CollectionsCategoriesPivot::class,'id_movie','id_movie');
    }
}
