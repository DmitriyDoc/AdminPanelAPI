<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MovieInfo extends Model
{
    use HasFactory;
    protected $table = 'movies_info';

    protected $fillable = [
        'id',
        'id_movie',
        'type_film',
        'title',
        'original_title',
        'year_release',
        'restrictions',
        'runtime',
        'rating',
        'companies',
        'budget',
        'created_at',
        'updated_at',
    ];

    public function posterFeatureFilm()
    {
        return $this->hasMany(PostersFeatureFilm::class,'id_movie','id_movie')->oldest();
    }
    public function posterMiniSeries()
    {
        return $this->hasMany(PostersMiniSeries::class,'id_movie','id_movie')->oldest();
    }
    public function posterShortFilm()
    {
        return $this->hasMany(PostersShortFilm::class,'id_movie','id_movie')->oldest();
    }
    public function posterTvMovie()
    {
        return $this->hasMany(PostersTvMovie::class,'id_movie','id_movie')->oldest();
    }
    public function posterTvSeries()
    {
        return $this->hasMany(PostersTvSeries::class,'id_movie','id_movie')->oldest();
    }
    public function posterTvShort()
    {
        return $this->hasMany(PostersTvShort::class,'id_movie','id_movie')->oldest();
    }
    public function posterTvSpecial()
    {
        return $this->hasMany(PostersTvSpecial::class,'id_movie','id_movie')->oldest();
    }
    public function posterVideo()
    {
        return $this->hasMany(PostersVideo::class,'id_movie','id_movie')->oldest();
    }

    public function collection()
    {
        return $this->hasMany(CollectionsCategoriesPivot::class,'id_movie','id_movie');
    }
    public function localazingRu()
    {
        return $this->hasOne(MoviesInfoRu::class,'id_movie','id_movie');
    }
    public function localazingEn()
    {
        return $this->hasOne(MoviesInfoEn::class,'id_movie','id_movie');
    }
    public function assignPoster()
    {
        return $this->hasOne(AssignPoster::class,'id_movie','id_movie');
    }
    public function categories()
    {
        return $this->hasMany(CollectionsCategoriesPivot::class,'id_movie','id_movie');
    }
}
