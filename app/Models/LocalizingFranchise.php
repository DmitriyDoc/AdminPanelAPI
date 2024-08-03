<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalizingFranchise extends Model
{
    use HasFactory;
    protected $guarded = false;
    protected $table = 'localizing_franchise';
    public $timestamps = false;
}
