<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImdbChart extends Model
{
    protected $table = 'imdb_charts';

    protected $fillable = [
        'chart_id',
        'title_en',
        'title_ru',
        'url',
        'movies_count',
        'last_parsed_at',
    ];

    protected $casts = [
        'movies_count' => 'integer',
        'last_parsed_at' => 'datetime',
    ];

    /**
     * Связь с фильмами в этом списке
     */
    public function movies(): HasMany
    {
        return $this->hasMany(ImdbChartMovie::class, 'chart_id', 'chart_id')
            ->orderBy('position');
    }

    /**
     * Получить все movie_id этого списка
     */
    public function getMovieIds(): array
    {
        return $this->movies()->pluck('movie_id')->toArray();
    }

}
