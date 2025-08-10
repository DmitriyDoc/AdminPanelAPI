<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoviesInfoEn extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'localizing_info_movies_en';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'id_movie',
        'genres',
        'cast',
        'directors',
        'writers',
        'story_line',
        'release_date',
        'countries',
    ];
}
