<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CelebsInfo extends Model
{
    use HasFactory;
    protected $table = 'celebs_info';
    //protected $guarded = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'id_celeb',
        'photo',
        'knowfor',
        'birthday',
        'died',
        'created_at',
        'updated_at',
    ];
    protected $dates = [
        'created_at',
        'updated_at'
    ];
}
