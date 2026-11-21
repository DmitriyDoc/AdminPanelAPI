<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionsCategoriesPivot extends Model
{
    use HasFactory;

    protected $table = 'collections_categories_pivot';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'collection_id' => 'integer',
        'franchise_id'  => 'integer',
        'type_film'     => 'integer',
        'viewed'        => 'boolean',
        'short'         => 'boolean',
        'adult'         => 'boolean',
    ];
}
