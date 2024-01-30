<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IdTypeCelebs extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'celebs_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'actor_id',
        'name',
        'created_at',
        'updated_at',
    ];
    public function poster()
    {
        return $this->hasOne(InfoCelebs::class,'id_celeb','actor_id');
    }
    public function getCountAttribute(){
        return $this->count();
    }
    public function getLastDayCountAttribute(){

        return $this->where('created_at','>=',Carbon::now()->subdays(1))->count();
    }
}
