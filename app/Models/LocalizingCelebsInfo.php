<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalizingInfoMovies extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'localizing_info_movies';
    public $timestamps = false;
}
