<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;
    protected $table = 'collections';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'category_id',
        'value',
        'label',
        'label_ru',
        'created_at',
        'updated_at',
    ];

    public function category()
    {
        return $this->hasMany(Category::class,'id','category_id');
    }

    public function children()
    {
        return $this->hasMany(CollectionsFranchisesPivot::class,'collection_id','id')->orderBy('id','desc');
    }
}
