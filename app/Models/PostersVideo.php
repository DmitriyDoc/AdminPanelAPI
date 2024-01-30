<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostersVideo extends Model
{
    use HasFactory;
    protected $table = 'movies_posters_video';

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
