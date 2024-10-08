<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalizingCelebsInfo extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'localizing_celebs_info';
    public $timestamps = false;
}
