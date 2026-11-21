<?php

namespace App\Services\TMDb;

use App\Events\CurrentPercentageEvent;
use App\Models\MovieInfo;
use App\Models\MoviesInfoEn;
use App\Models\MoviesInfoRu;
use App\Models\Tag;
use App\Models\TagsMoviesPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TmdbMovieUpdateService extends BaseTmdbService
{
    protected array $allowedTableNames = [
        'FeatureFilm' => ['segment' => 'feature_film', 'type' => 'movie', 'type_film' => 1],
        'MiniSeries' => ['segment' => 'mini_series', 'type' => 'tv', 'type_film' => 2],
        'ShortFilm' => ['segment' => 'short_film', 'type' => 'movie', 'type_film' => 3],
        'TvMovie' => ['segment' => 'tv_movie', 'type' => 'movie', 'type_film' => 4],
        'TvSeries' => ['segment' => 'tv_series', 'type' => 'tv', 'type_film' => 5],
        'TvShort' => ['segment' => 'tv_short', 'type' => 'tv', 'type_film' => 6],
        'TvSpecial' => ['segment' => 'tv_special', 'type' => 'tv', 'type_film' => 7],
        'Video' => ['segment' => 'video', 'type' => 'movie', 'type_film' => 8],
    ];

    public function processUpdate(array $movieData, string $type): void
    {
        $imdbId = $movieData['id'];
        $typeSync = $movieData['typeSync'] ?? 'unknown';

        Log::info("📥 TMDB Received Payload", [
            'imdb_id' => $imdbId,
            'type' => $type,
            'typeSync' => $typeSync
        ]);

        if (!array_key_exists($type, $this->allowedTableNames)) {
            Log::warning("TMDB Service: Unknown type '{$type}' for {$imdbId}. Skipping.");
            return;
        }

        $config = $this->allowedTableNames[$type];
        $mediaType = $config['type'];
        $typeFilmId = $config['type_film'];

        $resolution = $this->attemptToResolveTmdbId($imdbId, $mediaType, $typeFilmId);

        if (!$resolution) {
            Log::warning("TMDB Service: IMDB ID {$imdbId} not found in TMDB at all (neither as movie nor as tv). Skipping.");
            return;
        }

        $tmdbId = $resolution['tmdbId'];
        $mediaType = $resolution['mediaType'];
        $typeFilmId = $resolution['typeFilmId'];


        if ($typeSync === 'full_sync') {
            //Log::info("🔄 TMDB [FULL SYNC] Starting hard sync for {$imdbId}");
            //$this->syncInfo($imdbId, $mediaType, $tmdbId, $typeFilmId);
            $this->syncPosters($imdbId, $tmdbId, $mediaType);
            $this->syncImages($imdbId, $tmdbId, $mediaType);
            $this->syncLogos($imdbId, $tmdbId, $mediaType);
            event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
            return;
        }

        if ($typeSync === 'import_sync') {
            $exists = DB::table('movies_info')->where('id_movie', $imdbId)->exists();
            if ($exists) {
                Log::warning("🚫 TMDB [IMPORT SYNC] Movie {$imdbId} already exists in DB. Skipping.");
                return;
            }

            //Log::info("🆕 TMDB [IMPORT SYNC] Starting first-time creation for {$imdbId}");
            $this->syncInfo($imdbId, $mediaType, $tmdbId, $typeFilmId);
            $this->syncPosters($imdbId, $tmdbId, $mediaType);
            $this->syncImages($imdbId, $tmdbId, $mediaType);
            $this->syncLogos($imdbId, $tmdbId, $mediaType);
            event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
            return;
        }
        if ($typeSync === 'info_sync') {
            //Log::info("🔄 TMDB [INFO SYNC] Starting hard sync for {$imdbId}");
            $this->syncInfo($imdbId, $mediaType, $tmdbId, $typeFilmId);
            event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
            return;
        }

        if ($typeSync === 'posters_sync') {
            //Log::info("🖼️ TMDB [POSTERS SYNC] Hard updating posters for {$imdbId}");
            $this->syncPosters($imdbId, $tmdbId, $mediaType);
            event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
            return;
        }

        if ($typeSync === 'images_sync') {
            //Log::info("🏞️ TMDB [IMAGES SYNC] Hard updating backdrops for {$imdbId}");
            $this->syncImages($imdbId, $tmdbId, $mediaType);
            event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
            return;
        }

        Log::warning("TMDB Service: Unknown typeSync '{$typeSync}' for {$imdbId}. Skipping.");
    }

    private function syncInfo(string $imdbId, string $mediaType, int $tmdbId, int $typeFilmId): void
    {
        $enDetails = $this->makeRequest("/{$mediaType}/{$tmdbId}", [
            'language' => 'en-US',
            'append_to_response' => 'credits,release_dates,content_ratings,translations'
        ]);

        $ruDetails = $this->makeRequest("/{$mediaType}/{$tmdbId}", [
            'language' => 'ru-RU',
            'append_to_response' => 'credits,release_dates,content_ratings'
        ]);

        $originalLanguage = $enDetails['original_language'] ?? null;
        $originalTitle = $enDetails['original_title'] ?? $enDetails['original_name'] ?? '';

        $ruTitle = $this->getRussianTitle($enDetails, $originalTitle);
        $personImdbMap = $this->getPersonImdbIds($enDetails);

        $safeBudget = $this->getResolvedBudget($imdbId, $enDetails['budget'] ?? null);

        $existingRuData = MoviesInfoRu::where('id_movie', $imdbId)->first();

        $movieInfoData = $this->prepareMovieInfo($imdbId, $typeFilmId, $ruTitle, $originalTitle, $enDetails, $mediaType, $safeBudget);
        $enData = $this->prepareLocalizationData($imdbId, $enDetails, $personImdbMap, 'en-US', 'US');
        $ruData = $this->prepareLocalizationData($imdbId, $ruDetails, $personImdbMap, 'ru-RU', 'RU', $enDetails, $existingRuData);

        try {
            if (empty($movieInfoData) && empty($enData) && empty($ruData)) {
                Log::warning("⚠️ [INFO] No data to save for {$imdbId}. Skipping.");
                return;
            }
            DB::beginTransaction();

            MovieInfo::updateOrCreate(['id_movie' => $imdbId], $movieInfoData);
            MoviesInfoEn::updateOrCreate(['id_movie' => $imdbId], $enData);
            MoviesInfoRu::updateOrCreate(['id_movie' => $imdbId], $ruData);

            $this->syncTags($imdbId, $typeFilmId, $enDetails, $ruDetails);

            DB::commit();
            event(new CurrentPercentageEvent(['percent'=>0,'action'=>__('parser.general_data_parser'),'color'=>'']));
            //Log::info("💾 [INFO] Successfully saved all info and tags for {$imdbId}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to save movie info for {$imdbId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function getResolvedBudget(string $imdbId, ?int $tmdbBudget): ?string
    {
        $formattedTmdbBudget = null;
        if ($tmdbBudget > 0) {
            $formattedTmdbBudget = '$' . number_format($tmdbBudget, 0, '.', ',');
        }

        $existingBudget = DB::table('movies_info')->where('id_movie', $imdbId)->value('budget');

        if ($existingBudget !== null && trim((string)$existingBudget) !== '') {
            return $existingBudget;
        }

        return $formattedTmdbBudget;
    }

    private function getRussianTitle(array $details, string $originalTitle): string
    {
        $translations = $details['translations']['translations'] ?? [];
        foreach ($translations as $t) {
            if (($t['iso_3166_1'] ?? '') === 'RU') {
                $ruTitle = $t['data']['title'] ?? $t['data']['name'] ?? '';
                if (!empty($ruTitle)) {
                    return $ruTitle;
                }
            }
        }
        $enTitle = $details['title'] ?? $details['name'] ?? '';
        return !empty($enTitle) ? $enTitle : $originalTitle;
    }

    private function getPersonImdbIds(array $details): array
    {
        $credits = $details['credits'] ?? [];
        $cast = $credits['cast'] ?? [];
        $crew = $credits['crew'] ?? [];
        $personIds = [];

        foreach (array_slice($cast, 0, 20) as $actor) {
            $personIds[] = $actor['id'];
        }
        foreach ($crew as $member) {
            if (($member['job'] ?? '') === 'Director' || ($member['department'] ?? '') === 'Writing') {
                $personIds[] = $member['id'];
            }
        }

        $personIds = array_unique($personIds);
        if (empty($personIds)) return [];

        $map = [];
        try {
            $responses = Http::pool(function ($pool) use ($personIds) {
                foreach ($personIds as $personId) {
                    $pool->as((string)$personId)->get($this->baseUrl . "/person/{$personId}/external_ids?api_key={$this->apiKey}");
                }
            });

            foreach ($responses as $personId => $response) {
                if ($response instanceof \Throwable) {
                    Log::warning("⚠️ Network error fetching external_ids for person {$personId}: " . $response->getMessage());
                    continue; // Пропускаем этого актера, но не ломаем весь процесс
                }

                if ($response->successful() && !empty($response->json()['imdb_id'])) {
                    $map[$personId] = $response->json()['imdb_id'];
                }
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Failed to fetch person external_ids pool: " . $e->getMessage());
        }

        return $map;
    }

    private function prepareMovieInfo(string $imdbId, int $typeFilmId, string $title, string $originalTitle, array $details, string $mediaType, ?string $safeBudget): array
    {
        $runtimeMinutes = $details['runtime'] ?? ($details['episode_run_time'][0] ?? null);
        $runtime = $runtimeMinutes ? $this->formatRuntime($runtimeMinutes) : null;

        $releaseDate = $details['release_date'] ?? $details['first_air_date'] ?? null;
        $yearRelease = $releaseDate ? substr($releaseDate, 0, 4) : null;

        $rating = $details['vote_average'] ?? null;
        $rating = ($rating > 0) ? round((float)$rating, 1) : null;

        $restrictions = $this->getRestriction($details, 'US');

        $companies = [];
        foreach ($details['production_companies'] ?? [] as $company) {
            $companies[] = $company['name'];
        }

        return [
            'id_movie' => $imdbId,
            'type_film' => $typeFilmId,
            'title' => $title,
            'original_title' => $originalTitle,
            'year_release' => $yearRelease,
            'runtime' => $runtime,
            'rating' => $rating,
            'restrictions' => $restrictions,
            'companies' => !empty($companies) ? serialize(['companies' => $companies]) : null,
            'budget' => $safeBudget,
            'published' => 0,
        ];
    }

    private function prepareLocalizationData(string $imdbId, array $details, array $personImdbMap, string $language, string $countryCode, ?array $enDetails = null, ?object $existingRuData = null): array
    {
        $genres = [];
        foreach ($details['genres'] ?? [] as $genre) {
            $genres[] = mb_ucfirst(mb_strtolower($genre['name']));
        }

        $countries = [];
        foreach ($details['production_countries'] ?? [] as $country) {
            $code = $country['iso_3166_1'] ?? 'XX';
            $countries[] = $this->getCountryName($code, $language === 'ru-RU');
        }

        // ==========================================
        // 🔥 УМНАЯ ЗАЩИТА СЮЖЕТА (STORY LINE)
        // ==========================================
        $newStoryLine = $details['overview'] ?? null;
        if (empty($newStoryLine) && $language === 'ru-RU' && $enDetails) {
            $newStoryLine = $enDetails['overview'] ?? null;
        }

        $finalStoryLine = $newStoryLine;

        if ($language === 'ru-RU') {
            $existingStoryLine = $existingRuData->story_line ?? null;

            // Нормализуем: если в БД по ошибке массив, превращаем в строку для проверки и сохранения
            $storyLineCheck = is_array($existingStoryLine) ? implode(' ', $existingStoryLine) : (string)$existingStoryLine;

            if (!empty($existingStoryLine) && $this->isCyrillic($storyLineCheck)) {
                $finalStoryLine = is_array($existingStoryLine) ? implode(' ', $existingStoryLine) : $existingStoryLine;
            }
        }
        // ==========================================

        $releaseDateFormatted = $this->formatReleaseDateWithFallback($details, $countryCode, $language === 'ru-RU');

        $credits = $details['credits'] ?? [];

        $newCastJson = $this->formatCast($credits['cast'] ?? [], $personImdbMap);
        $newDirectorsJson = $this->formatDirectors($credits['crew'] ?? [], $personImdbMap);
        $newWritersJson = $this->formatWriters($credits['crew'] ?? [], $personImdbMap);

        if ($language === 'ru-RU') {
            $finalCastJson = $this->mergeCastData($existingRuData->cast ?? null, $newCastJson, $language);
            $finalDirectorsJson = $this->mergeCrewData($existingRuData->directors ?? null, $newDirectorsJson, $language);
            $finalWritersJson = $this->mergeCrewData($existingRuData->writers ?? null, $newWritersJson, $language);
        } else {
            $finalCastJson = $newCastJson;
            $finalDirectorsJson = $newDirectorsJson;
            $finalWritersJson = $newWritersJson;
        }

        return [
            'id_movie' => $imdbId,
            'genres' => !empty($genres) ? json_encode($genres) : null,
            'countries' => !empty($countries) ? json_encode($countries) : null,
            'story_line' => $finalStoryLine,
            'release_date' => $releaseDateFormatted,
            'cast' => $finalCastJson,
            'directors' => $finalDirectorsJson,
            'writers' => $finalWritersJson,
        ];
    }
    private function isCyrillic($text): bool
    {
        // Если вдруг пришел массив, склеиваем его в строку через пробел
        if (is_array($text)) {
            $text = implode(' ', $text);
        }

        // Если это не строка (например, null или объект), считаем, что кириллицы нет
        if (!is_string($text)) {
            return false;
        }

        return (bool) preg_match('/[а-яА-ЯёЁ]/u', $text);
    }
    /**
     * Умное слияние данных съемочной группы (режиссеры, сценаристы)
     * Сохраняет русское имя, если оно уже было переведено вручную
     */
    private function mergeCastData(?string $existingCastJson, ?string $newCastJson, string $language): ?string
    {
        if ($language !== 'ru-RU') {
            return $newCastJson;
        }

        $existingCast = $existingCastJson ? json_decode($existingCastJson, true) : [];
        $newCast = $newCastJson ? json_decode($newCastJson, true) : [];
        $mergedCast = $existingCast;

        foreach ($newCast as $nmId => $newPerson) {
            if (isset($mergedCast[$nmId])) {
                $existingPerson = $mergedCast[$nmId];

                // 🔥 Нормализуем в строку, если вдруг в БД попал массив
                $exRole = is_array($existingPerson['role'] ?? '') ? implode(' ', $existingPerson['role']) : (string)($existingPerson['role'] ?? '');
                $exActor = is_array($existingPerson['actor'] ?? '') ? implode(' ', $existingPerson['actor']) : (string)($existingPerson['actor'] ?? '');

                $mergedCast[$nmId] = [
                    'role' => $this->isCyrillic($exRole) ? $exRole : ($newPerson['role'] ?? $exRole),
                    'actor' => $this->isCyrillic($exActor) ? $exActor : ($newPerson['actor'] ?? $exActor),
                ];
            } else {
                $mergedCast[$nmId] = $newPerson;
            }
        }

        return !empty($mergedCast) ? json_encode($mergedCast, JSON_UNESCAPED_UNICODE) : null;
    }

    private function mergeCrewData(?string $existingCrewJson, ?string $newCrewJson, string $language): ?string
    {
        if ($language !== 'ru-RU') {
            return $newCrewJson;
        }

        $existingCrew = $existingCrewJson ? json_decode($existingCrewJson, true) : [];
        $newCrew = $newCrewJson ? json_decode($newCrewJson, true) : [];
        $mergedCrew = $existingCrew;

        foreach ($newCrew as $nmId => $newName) {
            if (isset($mergedCrew[$nmId])) {
                // 🔥 Нормализуем в строку, если в БД по ошибке записан массив
                $exName = is_array($existingCrew[$nmId]) ? implode(' ', $existingCrew[$nmId]) : (string)$existingCrew[$nmId];

                $mergedCrew[$nmId] = $this->isCyrillic($exName) ? $exName : $newName;
            } else {
                $mergedCrew[$nmId] = $newName;
            }
        }

        return !empty($mergedCrew) ? json_encode($mergedCrew, JSON_UNESCAPED_UNICODE) : null;
    }

    // ==========================================
    // СТАРЫЕ РАБОЧИЕ МЕТОДЫ (Без изменений)
    // ==========================================

    private function syncTags(string $imdbId, int $typeFilmId, array $enDetails, array $ruDetails): void
    {
        TagsMoviesPivot::where('id_movie', $imdbId)->where('type_film', $typeFilmId)->delete();

        $enGenres = $enDetails['genres'] ?? [];
        $ruGenres = $ruDetails['genres'] ?? [];

        foreach ($enGenres as $index => $enGenre) {
            $enName = mb_ucfirst(mb_strtolower($enGenre['name']));
            $ruName = isset($ruGenres[$index]) ? mb_ucfirst(mb_strtolower($ruGenres[$index]['name'])) : $enName;
            $slug = Str::slug($enName);

            $tag = Tag::firstOrCreate(
                ['value' => $slug],
                [
                    'tag_name_en' => $enName,
                    'tag_name_ru' => $ruName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            TagsMoviesPivot::updateOrCreate(
                [
                    'id_movie' => $imdbId,
                    'id_tag' => $tag->id,
                    'type_film' => $typeFilmId
                ]
            );
        }

        Log::info("🏷️ [TAGS] Synced " . count($enGenres) . " tags for {$imdbId}");
    }

    private function formatCast(array $cast, array $personImdbMap): ?string
    {
        $result = [];
        $roleCounts = [];
        $actorCounts = [];

        foreach ($cast as $actor) {
            $role = trim($actor['character'] ?? '');
            $name = trim($actor['name'] ?? '');

            if (!empty($role)) {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            }
            if (!empty($name)) {
                $actorCounts[$name] = ($actorCounts[$name] ?? 0) + 1;
            }
        }

        foreach ($cast as $actor) {
            $imdbId = $personImdbMap[$actor['id']] ?? null;
            if (!$imdbId) continue;

            $role = trim($actor['character'] ?? 'Unknown');
            $name = trim($actor['name'] ?? 'Unknown');

            $isRoleSuspicious = isset($roleCounts[$role]) && $roleCounts[$role] >= 3;
            $isActorSuspicious = isset($actorCounts[$name]) && $actorCounts[$name] > 2;

            if ($isRoleSuspicious || $isActorSuspicious) {
                continue;
            }

            $result[$imdbId] = [
                'role' => $role,
                'actor' => $name
            ];
        }

        return !empty($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : null;
    }

    private function formatDirectors(array $crew, array $personImdbMap): ?string
    {
        $result = [];
        foreach ($crew as $member) {
            if (($member['job'] ?? '') !== 'Director') continue;
            $imdbId = $personImdbMap[$member['id']] ?? null;
            if (!$imdbId) continue;
            $result[$imdbId] = $member['name'] ?? 'Unknown';
        }
        return !empty($result) ? json_encode($result) : null;
    }

    private function formatWriters(array $crew, array $personImdbMap): ?string
    {
        $result = [];
        foreach ($crew as $member) {
            if (($member['department'] ?? '') !== 'Writing') continue;
            $imdbId = $personImdbMap[$member['id']] ?? null;
            if (!$imdbId) continue;
            $result[$imdbId] = $member['name'] ?? 'Unknown';
        }
        return !empty($result) ? json_encode($result) : null;
    }

    private function formatRuntime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) return "{$hours}h {$mins}m";
        if ($hours > 0) return "{$hours}h";
        return "{$mins}m";
    }

    private function getRestriction(array $details, string $countryCode): ?string
    {
        $releaseDates = $details['release_dates']['results'] ?? [];
        foreach ($releaseDates as $country) {
            if (($country['iso_3166_1'] ?? '') === $countryCode) {
                foreach ($country['release_dates'] as $rd) {
                    if (!empty($rd['certification'])) return $rd['certification'];
                }
            }
        }
        $contentRatings = $details['content_ratings']['results'] ?? [];
        foreach ($contentRatings as $country) {
            if (($country['iso_3166_1'] ?? '') === $countryCode) {
                return $country['rating'] ?? null;
            }
        }
        return null;
    }

    private function formatReleaseDateWithFallback(array $details, string $targetCountryCode, bool $forceRussianCountryName): ?string
    {
        $releaseDates = $details['release_dates']['results'] ?? [];

        foreach ($releaseDates as $country) {
            if (($country['iso_3166_1'] ?? '') === $targetCountryCode) {
                foreach ($country['release_dates'] as $rd) {
                    if (!empty($rd['release_date'])) {
                        return $this->buildDateString($rd['release_date'], $targetCountryCode, $forceRussianCountryName);
                    }
                }
            }
        }

        if ($targetCountryCode === 'RU') {
            foreach ($releaseDates as $country) {
                if (($country['iso_3166_1'] ?? '') === 'US') {
                    foreach ($country['release_dates'] as $rd) {
                        if (!empty($rd['release_date'])) {
                            return $this->buildDateString($rd['release_date'], 'US', true);
                        }
                    }
                }
            }
        }

        foreach ($releaseDates as $country) {
            foreach ($country['release_dates'] as $rd) {
                if (!empty($rd['release_date']) && $rd['type'] === 3) {
                    $countryCode = $country['iso_3166_1'] ?? 'WW';
                    return $this->buildDateString($rd['release_date'], $countryCode, $forceRussianCountryName);
                }
            }
        }

        if (!empty($details['first_air_date'])) {
            return $this->buildDateString($details['first_air_date'], $targetCountryCode, $forceRussianCountryName);
        }

        return null;
    }

    private function buildDateString(string $dateStr, string $countryCode, bool $forceRussian): ?string
    {
        $timestamp = strtotime($dateStr);
        if (!$timestamp) return null;

        if ($forceRussian) {
            $day = date('j', $timestamp);
            $monthNum = (int)date('n', $timestamp);
            $year = date('Y', $timestamp);

            $monthsRu = [
                1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
                5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
                9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
            ];

            $formatted = "{$day} {$monthsRu[$monthNum]}, {$year}";
        } else {
            $formatted = date('F j, Y', $timestamp);
        }

        $countryName = $this->getCountryName($countryCode, $forceRussian);
        return "{$formatted} ({$countryName})";
    }

    private function getCountryName(string $code, bool $forceRussian = false): string
    {
        if ($forceRussian) {
            $ruCountries = [
                'US' => 'США', 'RU' => 'Россия', 'GB' => 'Великобритания', 'CA' => 'Канада',
                'DE' => 'Германия', 'FR' => 'Франция', 'IT' => 'Италия', 'ES' => 'Испания',
                'JP' => 'Япония', 'KR' => 'Южная Корея', 'CN' => 'Китай', 'IN' => 'Индия',
                'BR' => 'Бразилия', 'MX' => 'Мексика', 'AU' => 'Австралия', 'NZ' => 'Новая Зеландия',
                'ZA' => 'ЮАР', 'SE' => 'Швеция', 'NO' => 'Норвегия', 'DK' => 'Дания',
                'FI' => 'Финляндия', 'PL' => 'Польша', 'CZ' => 'Чехия', 'HU' => 'Венгрия',
                'RO' => 'Румыния', 'BG' => 'Болгария', 'TR' => 'Турция', 'IL' => 'Израиль',
                'AE' => 'ОАЭ', 'EG' => 'Египет', 'TH' => 'Таиланд', 'ID' => 'Индонезия',
                'PH' => 'Филиппины', 'AR' => 'Аргентина', 'CL' => 'Чили', 'CO' => 'Колумбия',
                'PE' => 'Перу', 'VE' => 'Венесуэла', 'BE' => 'Бельгия', 'NL' => 'Нидерланды',
                'CH' => 'Швейцария', 'AT' => 'Австрия', 'IE' => 'Ирландия', 'PT' => 'Португалия',
                'GR' => 'Греция',
                'SU' => 'СССР', 'YU' => 'Югославия', 'CS' => 'Чехословакия', 'DD' => 'ГДР',
            ];
            return $ruCountries[$code] ?? $code;
        }

        $enCountries = [
            'US' => 'United States', 'RU' => 'Russia', 'GB' => 'United Kingdom', 'CA' => 'Canada',
            'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
            'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China', 'IN' => 'India',
            'BR' => 'Brazil', 'MX' => 'Mexico', 'AU' => 'Australia', 'NZ' => 'New Zealand',
            'ZA' => 'South Africa', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
            'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czech Republic', 'HU' => 'Hungary',
            'RO' => 'Romania', 'BG' => 'Bulgaria', 'TR' => 'Turkey', 'IL' => 'Israel',
            'AE' => 'United Arab Emirates', 'EG' => 'Egypt', 'TH' => 'Thailand', 'ID' => 'Indonesia',
            'PH' => 'Philippines', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
            'PE' => 'Peru', 'VE' => 'Venezuela', 'BE' => 'Belgium', 'NL' => 'Netherlands',
            'CH' => 'Switzerland', 'AT' => 'Austria', 'IE' => 'Ireland', 'PT' => 'Portugal',
            'GR' => 'Greece',
            'SU' => 'Soviet Union', 'YU' => 'Yugoslavia', 'CS' => 'Czechoslovakia', 'DD' => 'East Germany',
        ];
        return $enCountries[$code] ?? $code;
    }

    private function syncPosters(string $imdbId, int $tmdbId, string $mediaType): void
    {
        $originalLanguage = $this->getOriginalLanguage($mediaType, $tmdbId);
        $languages = $this->buildLanguagesList($originalLanguage);
        $imagesResponse = $this->makeRequest("/{$mediaType}/{$tmdbId}/images", ['include_image_language' => $languages]);
        $this->saveToDatabase($this->preparePosters($imagesResponse, $imdbId));
        event(new CurrentPercentageEvent(['percent'=>0,'action'=>__('parser.posters_by_id_parser'),'color'=>'']));
    }

    private function syncImages(string $imdbId, int $tmdbId, string $mediaType): void
    {
        $imagesResponse = $this->makeRequest("/{$mediaType}/{$tmdbId}/images", ['include_image_language' => 'null']);
        $this->saveToDatabase($this->prepareBackdrops($imagesResponse, $imdbId));
        event(new CurrentPercentageEvent(['percent'=>0,'action'=>__('parser.images_by_id_parser'),'color'=>'']));
    }

    private function syncLogos(string $imdbId, int $tmdbId, string $mediaType): void
    {
        $originalLanguage = $this->getOriginalLanguage($mediaType, $tmdbId);
        $languages = $this->buildLanguagesList($originalLanguage);
        $imagesResponse = $this->makeRequest("/{$mediaType}/{$tmdbId}/images", ['include_image_language' => $languages]);
        $this->saveToDatabase($this->prepareLogos($imagesResponse, $imdbId, $originalLanguage));
        event(new CurrentPercentageEvent(['percent'=>0,'action'=>__('parser.logos_parser'),'color'=>'']));
    }

    private function getOriginalLanguage(string $mediaType, int $tmdbId): ?string
    {
        $details = $this->makeRequest("/{$mediaType}/{$tmdbId}", ['language' => 'en-US']);
        return $details['original_language'] ?? null;
    }

    private function buildLanguagesList(?string $originalLanguage): string
    {
        $languages = ['ru', 'en'];
        if ($originalLanguage && !in_array($originalLanguage, $languages)) {
            $languages[] = $originalLanguage;
        }
        $languages[] = 'null';
        return implode(',', $languages);
    }

    private function preparePosters(array $tmdbImages, string $imdbId): array
    {
        $records = [];
        foreach ($tmdbImages['posters'] ?? [] as $img) {
            $records[] = [
                'id_movie'  => $imdbId,
                'locale'    => $img['iso_639_1'],
                'file_path' => $img['file_path'],
                'width'     => $img['width'] ?? null,
                'height'    => $img['height'] ?? null,
            ];
        }
        return ['target_table' => 'movies_posters', 'records' => $records];
    }

    private function prepareBackdrops(array $tmdbImages, string $imdbId): array
    {
        $records = [];

        foreach ($tmdbImages['backdrops'] ?? [] as $img) {
            if (!is_null($img['iso_639_1'])) continue;

            $records[] = [
                'id_movie'  => $imdbId,
                'locale'    => null,
                'file_path' => $img['file_path'],
                'width'     => $img['width'] ?? null,
                'height'    => $img['height'] ?? null,
                'vote_avg'  => $img['vote_average'] ?? 0, // Временно сохраняем для сортировки
            ];
        }

        usort($records, function ($a, $b) {
            return $b['vote_avg'] <=> $a['vote_avg'];
        });

        $records = array_slice($records, 0, 50);

        $finalRecords = [];
        foreach ($records as $record) {
            unset($record['vote_avg']);
            $finalRecords[] = $record;
        }

        return ['target_table' => 'movies_backdrops', 'records' => $finalRecords];
    }

    private function attemptToResolveTmdbId(string $imdbId, string $expectedMediaType, int $currentTypeFilmId): ?array
    {
        // =====================================================================
        // Если в будущем понадобится, просто уберите //
        // =====================================================================

//        $manualTmdbMap = [
//            'tt34867554' => 1533820,
//        ];
//
//        if (isset($manualTmdbMap[$imdbId])) {
//            Log::info("🛠️ TMDB Manual Override: Using mapped TMDB ID {$manualTmdbMap[$imdbId]} for {$imdbId}");
//            return [
//                'tmdbId' => $manualTmdbMap[$imdbId],
//                'mediaType' => $expectedMediaType,
//                'typeFilmId' => $currentTypeFilmId
//            ];
//        }

        // =====================================================================

        // Стандартный поиск через /find
        $findResponse = $this->makeRequest("/find/{$imdbId}", ['external_source' => 'imdb_id', 'language' => 'ru-RU']);
        $movieResults = $findResponse['movie_results'] ?? [];
        $tvResults = $findResponse['tv_results'] ?? [];

        // 1. ПРИОРИТЕТ: Проверяем ожидаемый тип
        if ($expectedMediaType === 'movie' && !empty($movieResults)) {
            return ['tmdbId' => $movieResults[0]['id'], 'mediaType' => 'movie', 'typeFilmId' => $currentTypeFilmId];
        }
        if ($expectedMediaType === 'tv' && !empty($tvResults)) {
            return ['tmdbId' => $tvResults[0]['id'], 'mediaType' => 'tv', 'typeFilmId' => $currentTypeFilmId];
        }

        // 2. ФОЛЛБЭК: Проверяем альтернативный тип
        if ($expectedMediaType === 'movie' && !empty($tvResults)) {
            Log::info("🔄 TMDB Resolve: IMDB ID {$imdbId} found as TV, but DB says Movie. Using TV data.");
            return ['tmdbId' => $tvResults[0]['id'], 'mediaType' => 'tv', 'typeFilmId' => $currentTypeFilmId];
        }
        if ($expectedMediaType === 'tv' && !empty($movieResults)) {
            Log::info("🔄 TMDB Resolve: IMDB ID {$imdbId} found as Movie, but DB says TV. Using Movie data.");
            return ['tmdbId' => $movieResults[0]['id'], 'mediaType' => 'movie', 'typeFilmId' => $currentTypeFilmId];
        }

        // =====================================================================
        // УРОВЕНЬ 2: АВТОМАТИЧЕСКИЙ ПОДБОР ПО НАЗВАНИЮ (Title Search Fallback)
        // Срабатывает ТОЛЬКО если /find вернул абсолютную пустоту
        // =====================================================================
        if (empty($movieResults) && empty($tvResults)) {
            $localMovie = \App\Models\MovieInfo::where('id_movie', $imdbId)->first();

            if ($localMovie) {
                // Формируем очередь названий для поиска: сначала оригинальное, потом русское
                $titlesToTry = array_filter([
                    $localMovie->original_title,
                    $localMovie->title
                ]);

                $year = $localMovie->year_release ?? null;

                foreach ($titlesToTry as $searchQuery) {
                    Log::info("🔍 TMDB Fallback: /find failed. Trying title search for: '{$searchQuery}' (Year: {$year})");

                    // CASE A: Ищем как ФИЛЬМ
                    $movieSearch = $this->makeRequest("/search/movie", [
                        'query' => $searchQuery,
                        'year' => $year,
                        'language' => 'ru-RU'
                    ]);

                    if (!empty($movieSearch['results'])) {
                        $found = $movieSearch['results'][0];
                        Log::info("✅ TMDB Fallback SUCCESS (Movie): Found ID {$found['id']} ('{$found['title']}') for {$imdbId}");
                        return [
                            'tmdbId' => $found['id'],
                            'mediaType' => 'movie',
                            'typeFilmId' => $currentTypeFilmId
                        ];
                    }

                    // CASE B: Ищем как СЕРИАЛ (если как фильм не нашли)
                    $tvSearch = $this->makeRequest("/search/tv", [
                        'query' => $searchQuery,
                        'first_air_date_year' => $year,
                        'language' => 'ru-RU'
                    ]);

                    if (!empty($tvSearch['results'])) {
                        $found = $tvSearch['results'][0];
                        Log::info("✅ TMDB Fallback SUCCESS (TV): Found ID {$found['id']} ('{$found['name']}') for {$imdbId}");
                        return [
                            'tmdbId' => $found['id'],
                            'mediaType' => 'tv',
                            'typeFilmId' => $currentTypeFilmId
                        ];
                    }
                }

                Log::warning("❌ TMDB Fallback FAILED: No results found by title for {$imdbId}");
            } else {
                Log::warning("❌ TMDB Fallback FAILED: Local movie record not found in DB for {$imdbId}");
            }
        }
        // =====================================================================

        // 3. Если вообще ничего не помогло
        return null;
    }
    private function prepareLogos(array $tmdbImages, string $imdbId, ?string $originalLanguage): array
    {
        $records = [];
        foreach ($tmdbImages['logos'] ?? [] as $img) {
            $locale = $img['iso_639_1'];
            $isEnglish = ($locale === 'en');
            $isOriginalLang = ($originalLanguage && $locale === $originalLanguage && $originalLanguage !== 'en');
            $isNoLanguage = is_null($locale);

            if (!$isEnglish && !$isOriginalLang && !$isNoLanguage) continue;

            $records[] = [
                'id_movie'  => $imdbId,
                'locale'    => $locale,
                'file_path' => $img['file_path'],
                'width'     => $img['width'] ?? null,
                'height'    => $img['height'] ?? null,
            ];
        }
        return ['target_table' => 'movies_logos', 'records' => $records];
    }

    private function saveToDatabase(array $data): void
    {
        if (empty($data['records'])) {
            Log::info("⚠️ No records to save for table {$data['target_table']}");
            return;
        }

        $tableName = $data['target_table'];
        $records = $data['records'];

        try {
            DB::table($tableName)->upsert(
                $records,
                ['file_path'],
                ['locale', 'width', 'height', 'updated_at']
            );
            Log::info("💾 [UPSERT] Successfully saved/updated in {$tableName}", ['total' => count($records)]);
        } catch (\Exception $e) {
            Log::error("❌ Failed to save to {$tableName}: " . $e->getMessage());
            throw $e;
        }
    }
}
