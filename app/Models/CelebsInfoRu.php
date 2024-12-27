<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CelebsInfoRu extends Model
{
    use HasFactory;
    //protected $guarded = false;
    protected $table = 'localizing_celebs_info_ru';


    protected $fillable = [
        'id',
        'id_celeb',
        'nameActor',
        'filmography',
        'birthdayLocation',
        'dieLocation',
    ];

    public function images()
    {
        return $this->hasMany(ImagesCelebs::class,'id_celeb','id_celeb');
    }

    public function info()
    {
        return $this->hasOne(CelebsInfo::class,'id_celeb','id_celeb');
    }
}
