<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdPostersShortFilm extends Model
{
    use HasFactory;
    protected $table = 'movies_id_posters_short_film';

    protected $fillable = [
        'id',
        'id_movie',
        'src',
        'srcset',
        'namesCelebsImg',
        'created_at',
        'updated_at',
    ];

}
