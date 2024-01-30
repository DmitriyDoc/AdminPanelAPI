<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdPostersTvShort extends Model
{
    use HasFactory;
    protected $table = 'movies_id_posters_tv_short';

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
