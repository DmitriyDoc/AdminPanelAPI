<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdImagesCelebs extends Model
{
    use HasFactory;
    protected $table = 'celebs_id_images';

    protected $fillable = [
        'id',
        'id_celeb',
        'id_images',
        'created_at',
        'updated_at',
    ];
}
