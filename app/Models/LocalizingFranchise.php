<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalizingFranchise extends Model
{
    use HasFactory;
    protected $table = 'franchises';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'value',
        'label',
        'label_ru',
        'created_at',
        'updated_at',
    ];
}
