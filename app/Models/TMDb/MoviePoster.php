<?php

namespace App\Models\TMDb;

/**
 * Модель постеров фильмов/сериалов
 *
 * Таблица: movies_posters
 * Локализация: ru, en, original_language, null
 */
class MoviePoster extends TmdbImageModel
{
    protected $table = 'movies_posters';

    /**
     * Доступные размеры для постеров (TMDB CDN)
     */
    public const AVAILABLE_SIZES = ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'];

    /**
     * Размер по умолчанию для фронтенда
     */
    public const DEFAULT_SIZE = 'w500';

    /**
     * Получить URL постера в стандартном размере
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->getUrlForSize(self::DEFAULT_SIZE);
    }
}
