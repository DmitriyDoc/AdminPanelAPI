<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag
{
    use HasFactory;
    protected $table = 'tags';
    protected $fillable = [
        'tag_name',
        'tag_name_ru',
    ];

}
