<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagesTvMovie extends Model
{
    use HasFactory;
    protected $table = 'movies_images_tv_movie';

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
