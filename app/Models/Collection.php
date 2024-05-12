<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'collections';
    public $timestamps = false;

    public function category()
    {
        return $this->hasMany(Category::class,'id','category_id');
    }

    public function children()
    {
        return $this->hasMany(Franchise::class,'collection_id','id')->orderBy('id','desc');
    }
}
