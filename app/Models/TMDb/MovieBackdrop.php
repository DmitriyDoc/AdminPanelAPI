<?php

namespace App\Models\TMDb;

/**
 * Модель задников (backdrops) фильмов/сериалов
 *
 * Таблица: movies_backdrops
 * Локализация: всегда null (No Language) — задники не содержат текста
 */
class MovieBackdrop extends TmdbImageModel
{
    protected $table = 'movies_backdrops';

    /**
     * Доступные размеры для задников (TMDB CDN)
     */
    public const AVAILABLE_SIZES = ['w300', 'w780', 'w1280', 'original'];

    /**
     * Размер по умолчанию для фронтенда
     */
    public const DEFAULT_SIZE = 'w1280';

    /**
     * Получить URL задника в стандартном размере
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->getUrlForSize(self::DEFAULT_SIZE);
    }


}
