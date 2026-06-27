<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImdbChartMovie extends Model
{
    protected $table = 'imdb_chart_movies';

    // 🔧 Указываем, что первичного ключа id нет
    public $incrementing = false;

    // 🔧 Указываем составной первичный ключ
    protected $primaryKey = ['chart_id', 'movie_id'];

    protected $fillable = [
        'chart_id',
        'movie_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Связь со списком
     */
    public function chart(): BelongsTo
    {
        return $this->belongsTo(ImdbChart::class, 'chart_id', 'chart_id');
    }
}
