<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagsMoviesPivot extends Model
{
    use HasFactory;
    protected $guarded = false;
    public $timestamps = false;
    protected $fillable = [
        'id_movie',
        'id_tag',
        'type_film',
    ];
    protected $table = 'tags_movies_pivot';

}
