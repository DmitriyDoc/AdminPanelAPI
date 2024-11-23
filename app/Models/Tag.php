<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;
    protected $table = 'tags';
    public $timestamps = false;
    protected $fillable = [
        'value',
        'tag_name',
        'tag_name_ru',
        'created_at',
        'updated_at',
    ];
    public function children()
    {
        return $this->hasMany(TagsMoviesPivot::class,'id_tag','id');
    }
}
