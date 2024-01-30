<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdImagesVideo extends Model
{
    use HasFactory;
    protected $table = 'movies_id_images_video';

    protected $fillable = [
        'id',
        'id_movie',
        'id_images',
        'created_at',
        'updated_at',
    ];
}
