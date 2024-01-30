<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InfoCelebs extends Model
{
    use HasFactory;
    protected $table = 'celebs_info';

    protected $fillable = [
        'id',
        'id_celeb',
        'nameActor',
        'photo',
        'knowfor',
        'filmography',
        'birthday',
        'birthdayLocation',
        'died',
        'dieLocation',
        'created_at',
        'updated_at',
    ];
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    public function images()
    {
        return $this->hasMany(ImagesCelebs::class,'id_celeb','id_celeb');
    }
}
