<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdTypeMovies extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movies_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'id_movie',
        'type_film',
        'created_at',
        'updated_at',
    ];

}
