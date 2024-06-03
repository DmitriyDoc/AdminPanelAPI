<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostersMiniSeries extends Model
{
    use HasFactory;
    protected $table = 'movies_posters_mini_series';

    protected $fillable = [
        'id',
        'id_movie',
        'src',
        'srcset',
        'namesCelebsImg',
        'created_at',
        'updated_at',
    ];
    public function assignPosters()
    {
        return $this->hasOne(AssignPoster::class,'id_movie','id_movie');
    }

}
