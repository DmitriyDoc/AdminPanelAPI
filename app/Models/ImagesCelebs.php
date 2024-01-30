<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagesCelebs extends Model
{
    use HasFactory;
    protected $table = 'celebs_images';

    protected $fillable = [
        'id',
        'id_celeb',
        'nameActor',
        'src',
        'srcset',
        'namesCelebsImg',
        'created_at',
        'updated_at',
    ];
}
