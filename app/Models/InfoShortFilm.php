<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InfoShortFilm extends Model
{
    use HasFactory;
    protected $table = 'movies_info_short_film';

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
        'genres',
        'cast',
        'directors',
        'writers',
        'story_line',
        'release_date',
        'countries',
        'companies',
        'budget',
        'created_at',
        'updated_at',
    ];
    public function poster()
    {
        return $this->hasOne(PostersShortFilm::class,'id_movie','id_movie')->oldest();
    }
    public function collection()
    {
        return $this->hasMany(CollectionsCategoriesPivot::class,'id_movie','id_movie');
    }
    public function localazing()
    {
        return $this->hasOne(LocalizingInfoMovies::class,'id_movie','id_movie');
    }
}
