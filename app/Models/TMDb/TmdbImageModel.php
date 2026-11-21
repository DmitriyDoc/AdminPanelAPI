<?php

namespace App\Models\TMDb;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

abstract class TmdbImageModel extends Model
{
    public const LOCALE_RU = 'ru';
    public const LOCALE_EN = 'en';

    protected $fillable = [
        'id_movie',
        'locale',
        'file_path',
        'width',
        'height',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * Accessor: формирует full_url на лету из file_path
     * Использование: $poster->full_url
     */
    protected function fullUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => env('TMDB_IMAGE_BASE_URL')."/t/p/original" . ($attributes['file_path'] ?? '')
        );
    }

    /**
     * Получить URL нужного размера
     */
    public function getUrlForSize(string $size = 'original'): string
    {
        return env('TMDB_IMAGE_BASE_URL')."/t/p/{$size}{$this->file_path}";
    }

    public function scopeForMovie(Builder $query, string $imdbId): Builder
    {
        return $query->where('id_movie', $imdbId);
    }

    public function scopeWithLocale(Builder $query, ?string $locale): Builder
    {
        return is_null($locale)
            ? $query->whereNull('locale')
            : $query->where('locale', $locale);
    }

    public function scopeRussian(Builder $query): Builder
    {
        return $query->where('locale', self::LOCALE_RU);
    }

    public function scopeEnglish(Builder $query): Builder
    {
        return $query->where('locale', self::LOCALE_EN);
    }

    public function scopeNoLanguage(Builder $query): Builder
    {
        return $query->whereNull('locale');
    }
}
