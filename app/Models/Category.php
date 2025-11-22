<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'categories';

    public function children()
    {
        return $this->hasMany(Collection::class,'category_id','id')->orderBy('id','desc');
    }
    public function movieInfo()
    {
        return $this->hasOne(MovieInfo::class, 'id_movie', 'index_id');
    }
}
