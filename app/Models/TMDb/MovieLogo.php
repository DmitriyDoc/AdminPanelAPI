<?php

namespace App\Models\TMDb;

/**
 * Модель логотипов фильмов/сериалов
 *
 * Таблица: movies_logos
 * Локализация: en, original_language, null
 * Логотипы обычно в формате PNG с прозрачностью
 */
class MovieLogo extends TmdbImageModel
{
    protected $table = 'movies_logos';

    /**
     * Доступные размеры для логов (TMDB CDN)
     */
    public const AVAILABLE_SIZES = ['w92', 'w154', 'w185', 'w300', 'w500', 'original'];

    /**
     * Размер по умолчанию для фронтенда
     */
    public const DEFAULT_SIZE = 'w300';

    /**
     * Получить URL логотипа в стандартном размере
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->getUrlForSize(self::DEFAULT_SIZE);
    }
}
