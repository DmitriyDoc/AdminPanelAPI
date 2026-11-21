<?php

namespace App\Services\TMDb;

use App\Models\CelebsInfo;
use App\Models\CelebsInfoEn;
use App\Models\CelebsInfoRu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbCelebUpdateService extends BaseTmdbService
{
    public function processUpdate(array $celebData): void
    {
        $inputId = $celebData['id'];
        $typeSync = $celebData['typeSync'] ?? 'full_sync';

        $tmdbPersonId = $inputId;
        $imdbPersonId = null;

        if (str_starts_with(strtolower($inputId), 'nm')) {
            $findResponse = $this->makeRequest("/find/{$inputId}", ['external_source' => 'imdb_id']);
            if (!empty($findResponse['person_results'])) {
                $tmdbPersonId = (string) $findResponse['person_results'][0]['id'];
                $imdbPersonId = $inputId;
            } else {
                Log::warning("⚠️ Could not resolve IMDb ID {$inputId} to TMDB ID. Aborting.");
                return;
            }
        }

        // 2.1. Запрос для EN данных (базовые данные, EN фильмография, external_ids, translations для имени)
        $personDetailsEn = $this->makeRequest("/person/{$tmdbPersonId}", [
            'language' => 'en-US',
            'append_to_response' => 'combined_credits,external_ids,images,translations'
        ]);

        // 2.2. Запрос для RU фильмографии (чтобы названия фильмов были на русском, если есть)
        $personDetailsRu = $this->makeRequest("/person/{$tmdbPersonId}", [
            'language' => 'ru-RU',
            'append_to_response' => 'combined_credits'
        ]);

        if (empty($personDetailsEn['id'])) {
            Log::warning("Celeb Service: Person {$tmdbPersonId} not found in TMDB.");
            return;
        }

        if (!$imdbPersonId) {
            $imdbPersonId = $personDetailsEn['external_ids']['imdb_id'] ?? null;
        }

        $idCeleb = $imdbPersonId ?: (string) $personDetailsEn['id'];

        $newFilmographyEn = $this->buildFilmographyStrict($personDetailsEn['combined_credits'] ?? []);
        $newFilmographyRu = $this->buildFilmographyStrict($personDetailsRu['combined_credits'] ?? []);

        $existingEn = CelebsInfoEn::where('id_celeb', $idCeleb)->first();
        $existingRu = CelebsInfoRu::where('id_celeb', $idCeleb)->first();
        $existingBase = CelebsInfo::where('id_celeb', $idCeleb)->first();

        $mergedFilmographyEn = $this->mergeOnlyNew($existingEn?->filmography, $newFilmographyEn);
        $mergedFilmographyRu = $this->mergeOnlyNew($existingRu?->filmography, $newFilmographyRu);


        try {
            DB::beginTransaction();

            // === celebs_info (базовая) ===
            $baseUpdate = [];
            if (empty($existingBase?->photo)) {
                $baseUpdate['photo'] = !empty($personDetailsEn['profile_path']) ? $this->buildFullUrl($personDetailsEn['profile_path']) : null;
            }
            if (empty($existingBase?->birthday)) {
                $baseUpdate['birthday'] = $personDetailsEn['birthday'] ?? null;
            }
            if (empty($existingBase?->died)) {
                $baseUpdate['died'] = $personDetailsEn['deathday'] ?? null;
            }

            // Берем knowfor из EN фильмографии (топ-4)
            if (empty($existingBase?->knowfor)) {
                $knowForIds = $this->extractKnowForFromFilmography($newFilmographyEn);
                $baseUpdate['knowfor'] = !empty($knowForIds) ? json_encode($knowForIds) : null;
            }

            if (!empty($baseUpdate)) {
                CelebsInfo::updateOrCreate(['id_celeb' => $idCeleb], $baseUpdate);
            } elseif (!$existingBase) {
                CelebsInfo::create(['id_celeb' => $idCeleb]);
            }

            // === localizing_celebs_info_en (строго EN данные) ===
            $enUpdate = [
                'filmography' => $mergedFilmographyEn,
                'nameActor' => $personDetailsEn['name'] ?? 'Unknown',
                'birthdayLocation' => $personDetailsEn['place_of_birth'] ?? null,
                'dieLocation' => $personDetailsEn['place_of_death'] ?? null,
            ];
            CelebsInfoEn::updateOrCreate(['id_celeb' => $idCeleb], $enUpdate);

            // === localizing_celebs_info_ru (RU имя + RU фильмография) ===
            $ruName = $this->getRussianName($personDetailsEn['translations']['translations'] ?? [], $personDetailsEn['name']);

            $ruUpdate = [
                'filmography' => $mergedFilmographyRu, // <-- Здесь теперь русские названия фильмов!
                'nameActor' => $ruName,
                'birthdayLocation' => $personDetailsEn['place_of_birth'] ?? null,
                'dieLocation' => $personDetailsEn['place_of_death'] ?? null,
            ];
            CelebsInfoRu::updateOrCreate(['id_celeb' => $idCeleb], $ruUpdate);

            // === celebs_images (полная замена) ===
            $imageRecords = [];
            foreach ($personDetailsEn['images']['profiles'] ?? [] as $img) {
                if (!empty($img['file_path'])) {
                    $imageRecords[] = [
                        'id_celeb' => $idCeleb,
                        'src' => $img['file_path'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::table('celebs_images')->where('id_celeb', $idCeleb)->delete();
            if (!empty($imageRecords)) {
                foreach (array_chunk($imageRecords, 100) as $chunk) {
                    DB::table('celebs_images')->insert($chunk);
                }
            }

            DB::commit();
            Log::info("✅ [CELEB] Successfully saved localized data for person {$idCeleb}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to save celeb info for {$idCeleb}: " . $e->getMessage());
            throw $e;
        }
    }

    private function extractKnowForFromFilmography(array $filmography): array
    {
        $knowFor = [];
        // Ищем актерские данные и в 'actor', и в 'actress'
        $actingData = $filmography['actor'] ?? $filmography['actress'] ?? [];

        if (!empty($actingData) && is_array($actingData)) {
            $knowFor = array_slice(array_keys($actingData), 0, 4);
        }
        return $knowFor;
    }


    private function buildFilmographyStrict(array $combinedCredits): array
    {
        $allTmdbIds = [];
        $mediaTypes = [];
        $creditsMap = [];

        $departments = ['actor' => 'Acting', 'writer' => 'Writing', 'producer' => 'Production', 'soundtrack' => 'Sound'];

        foreach ($departments as $ourKey => $tmdbDept) {
            $items = $tmdbDept === 'Acting' ? ($combinedCredits['cast'] ?? []) : ($combinedCredits['crew'] ?? []);

            foreach ($items as $credit) {
                if ($tmdbDept !== 'Acting' && ($credit['department'] ?? '') !== $tmdbDept) {
                    continue;
                }

                $tmdbId = $credit['id'] ?? null;
                if (!$tmdbId) continue;

                $mediaType = $credit['media_type'] ?? 'movie';
                $allTmdbIds[] = $tmdbId;
                $mediaTypes[$tmdbId] = $mediaType;

                if (!isset($creditsMap[$ourKey][$tmdbId])) {
                    $dateStr = $credit['release_date'] ?? $credit['first_air_date'] ?? null;
                    $creditsMap[$ourKey][$tmdbId] = [
                        'role' => $credit['character'] ?? ($credit['job'] ?? ''),
                        'year' => $dateStr ? substr($dateStr, 0, 4) : null,
                        'title' => $credit['title'] ?? $credit['name'] ?? 'Unknown',
                    ];
                }
            }
        }

        $allTmdbIds = array_unique($allTmdbIds);
        $imdbMap = $this->getMediaImdbIdsSafe($allTmdbIds, $mediaTypes);

        $result = [];
        foreach ($departments as $ourKey => $tmdbDept) {
            if (!isset($creditsMap[$ourKey])) continue;

            $dept = [];
            foreach ($creditsMap[$ourKey] as $tmdbId => $data) {
                $imdbId = $imdbMap[$tmdbId] ?? null;

                // Добавляем ТОЛЬКО если есть валидный IMDb ID (начинается с 'tt')
                if ($imdbId && str_starts_with($imdbId, 'tt')) {
                    $dept[$imdbId] = [
                        'role' => $data['role'],
                        'year' => $data['year'],
                        'title' => $data['title'],
                    ];
                }
            }

            if (!empty($dept)) {
                $result[$ourKey] = $dept;
            }
        }

        return $result;
    }

    /**
     * 🔥 БЕЗОПАСНЫЙ ПАКЕТНЫЙ ЗАПРОС IMDb ID с задержками
     */
    private function getMediaImdbIdsSafe(array $tmdbIds, array $mediaTypes): array
    {
        if (empty($tmdbIds)) return [];

        $map = [];
        $chunkSize = 20;
        $chunkedIds = array_chunk($tmdbIds, $chunkSize);
        $totalChunks = count($chunkedIds);

        try {
            foreach ($chunkedIds as $chunkIndex => $chunk) {
                $responses = Http::pool(function ($pool) use ($chunk, $mediaTypes) {
                    foreach ($chunk as $id) {
                        $type = $mediaTypes[$id] ?? 'movie';
                        // Запрашиваем именно external_ids, это легче и быстрее
                        $pool->as((string)$id)->get($this->baseUrl . "/{$type}/{$id}/external_ids?api_key={$this->apiKey}");
                    }
                });

                foreach ($responses as $tmdbId => $response) {
                    if ($response instanceof \Throwable || !$response->successful()) {
                        continue;
                    }

                    $data = $response->json();
                    $imdbId = $data['imdb_id'] ?? null;
                    if ($imdbId && str_starts_with($imdbId, 'tt')) {
                        $map[$tmdbId] = $imdbId;
                    }
                }

                // Задержка между чанками для защиты от бана
                if ($chunkIndex < $totalChunks - 1) {
                    usleep(500000); // 0.5 секунды
                }
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Failed to fetch media external_ids safely: " . $e->getMessage());
        }

        return $map;
    }

    private function mergeOnlyNew(?string $existingJson, array $newData): ?string
    {
        $existing = $existingJson ? json_decode($existingJson, true) : [];
        if (!is_array($existing)) $existing = [];

        $merged = $existing;
        $addedCount = 0;
        $upgradedCount = 0;

        foreach ($newData as $department => $movies) {
            // 🔥 ИСПРАВЛЕНИЕ: Определяем целевой ключ для актерской фильмографии.
            // Если из API пришел 'actor', но в базе исторически использовался 'actress',
            // то мы должны обновлять именно 'actress', чтобы не создавать дубль.
            $targetDept = $department;
            if ($department === 'actor') {
                if (isset($merged['actress'])) {
                    $targetDept = 'actress'; // Вливаем новые данные в существующий 'actress'
                } else {
                    $targetDept = 'actor';   // Или создаем/обновляем 'actor'
                }
            }

            if (!isset($merged[$targetDept])) {
                $merged[$targetDept] = [];
            }

            foreach ($movies as $imdbId => $details) {
                $newYear = $details['year'] ?? null;

                if (!isset($merged[$targetDept][$imdbId])) {
                    // 1. Совершенно новая запись -> добавляем
                    $merged[$targetDept][$imdbId] = $details;
                    $addedCount++;
                } else {
                    // 2. Запись уже существует. Проверяем, нужно ли её "улучшить"
                    $existingYear = $merged[$targetDept][$imdbId]['year'] ?? null;

                    // Если у старой записи НЕТ года, а у новой ЕСТЬ год -> заменяем
                    if (empty($existingYear) && !empty($newYear)) {
                        $merged[$targetDept][$imdbId] = $details;
                        $upgradedCount++;
                    }
                }
            }
        }

        // Сортируем каждый департамент по году (по убыванию)
        foreach ($merged as $dept => $movies) {
            uasort($merged[$dept], function ($a, $b) {
                $yearA = (int)($a['year'] ?? 0);
                $yearB = (int)($b['year'] ?? 0);

                if ($yearA === $yearB) {
                    return strcmp($a['title'] ?? '', $b['title'] ?? '');
                }

                return $yearB <=> $yearA;
            });
        }

        Log::info("📝 Filmography merge result", [
            'added_new_items' => $addedCount,
            'upgraded_missing_year_items' => $upgradedCount,
        ]);

        return !empty($merged) ? json_encode($merged, JSON_UNESCAPED_UNICODE) : null;
    }

    private function extractTopKnowForImdbIds(array $knownFor): array
    {
        $tmdbIdsToResolve = [];
        $mediaTypes = [];

        foreach (array_slice($knownFor, 0, 10) as $item) {
            if (!empty($item['id']) && in_array($item['media_type'] ?? '', ['movie', 'tv'])) {
                $tmdbIdsToResolve[] = $item['id'];
                $mediaTypes[$item['id']] = $item['media_type'] ?? 'movie';
            }
        }

        if (empty($tmdbIdsToResolve)) return [];

        $imdbMap = $this->getMediaImdbIdsSafe($tmdbIdsToResolve, $mediaTypes);

        $result = [];
        foreach ($tmdbIdsToResolve as $tmdbId) {
            if (!empty($imdbMap[$tmdbId]) && str_starts_with($imdbMap[$tmdbId], 'tt')) {
                $result[] = $imdbMap[$tmdbId];
            }
        }

        return array_unique($result);
    }

    private function getRussianName(array $translations, string $defaultName): string
    {
        foreach ($translations as $t) {
            if (($t['iso_3166_1'] ?? '') === 'RU' || ($t['iso_639_1'] ?? '') === 'ru') {
                $ruName = $t['data']['name'] ?? '';
                if (!empty($ruName) && $ruName !== $defaultName) {
                    return $ruName;
                }
            }
        }
        return $defaultName;
    }
}
