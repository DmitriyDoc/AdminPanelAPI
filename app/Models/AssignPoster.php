<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignPoster extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'assign_posters';
    public $timestamps = false;
}
