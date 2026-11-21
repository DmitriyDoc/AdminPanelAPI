<?php

namespace App\Http\Controllers;

use App\Models\CelebsInfoRu;
use App\Models\MoviesInfoRu;
use App\Models\Tag;
use \Dejurin\GoogleTranslateForFree;
use Illuminate\Support\Facades\Log;

class TranslatorController
{
    private $source = 'en';
    private $target = 'ru';
    private $attempts = 3;
    private $translator;
    private $localizingTable = null;
    private $columnId = null;
    private $columnKey = null;

    // Кэш в памяти, чтобы не переводить одно и то же дважды внутри одного запуска
    private static $memoryCache = [];

    private $filmRoleDictionary = [
        // Роли
        'director' => 'Режиссер',
        'producer' => 'Продюсер',
        'executive producer' => 'Исполнительный продюсер',
        'co-producer' => 'Сопродюсер',
        'composer' => 'Композитор',
        'writer' => 'Сценарист',
        'cinematographer' => 'Оператор-постановщик',
        'editor' => 'Монтажер',
        'uncredited' => 'без указания в титрах',
        'voice' => 'озвучка',
        'archive footage' => 'архивные кадры',
        'self' => 'в роли самого себя',
        'himself' => 'в роли самого себя',
        'herself' => 'в роли самой себя',

        // Жанры
        'action' => 'Боевик',
        'adventure' => 'Приключения',
        'animation' => 'Анимация',
        'biography' => 'Биография',
        'comedy' => 'Комедия',
        'crime' => 'Криминал',
        'documentary' => 'Документальный',
        'drama' => 'Драма',
        'family' => 'Семейный',
        'fantasy' => 'Фэнтези',
        'history' => 'Исторический',
        'horror' => 'Ужасы',
        'music' => 'Музыка',
        'musical' => 'Мюзикл',
        'mystery' => 'Детектив',
        'romance' => 'Мелодрама',
        'sci-fi' => 'Фантастика',
        'thriller' => 'Триллер',
        'war' => 'Военный',
        'western' => 'Вестерн',
        'short' => 'Короткометражка',
        'whodunnit' => 'кто это сделал',
    ];
    private $dataMovie = [
        'id_movie' => null, 'genres' => null, 'cast' => null, 'directors' => null,
        'writers' => null, 'story_line' => null, 'countries' => null, 'release_date' => null
    ];
    private $dataCeleb = [
        'id_celeb' => null, 'filmography' => null, 'nameActor' => null,
        'birthdayLocation' => null, 'dieLocation' => null,
    ];

    public function __construct()
    {
        $this->translator = new GoogleTranslateForFree();
    }

    private function startTranslate($inputText = null) {
        if (empty($inputText)) return null;

        $cacheKey = md5($inputText);
        if (isset(self::$memoryCache[$cacheKey])) {
            return self::$memoryCache[$cacheKey];
        }

//        Log::debug(">>> Sending request (len: " . mb_strlen($inputText) . ")");

        try {
            $result = $this->translator->translate($this->source, $this->target, $inputText, $this->attempts);

            $delay = (mb_strlen($inputText) < 50) ? 1 : 3;
            sleep($delay);

            $final = $result ?: $inputText;
            self::$memoryCache[$cacheKey] = $final;

            return $final;
        } catch (\Exception $e) {
            Log::error("Translate Error: " . $e->getMessage());
            sleep(2);
            return $inputText;
        }
    }

    private function updateOrCreateLocalizing($data){
        $localizingModel = $this->localizingTable::where($this->columnKey,$this->columnId)->first();
        if ($localizingModel){
            $this->localizingTable->query()->updateOrCreate([$this->columnKey=>$this->columnId],$data);
        } else {
            $this->localizingTable::create($data);
        }
    }
    public function titleTranslate($inputTitle) {
        return $this->startTranslate($inputTitle);
    }
    public function translateMovie($dataInfo, $movieId, $columnKey) {
        $this->columnId = $movieId;
        $this->columnKey = $columnKey;
        $this->dataMovie['id_movie'] = $dataInfo->id_movie;
        $this->localizingTable = new MoviesInfoRu();

        // ==================== GENRES ====================
        $this->dataMovie['genres'] = null;
        if (!empty($dataInfo->genres)) {
            $unpackGenres = json_decode($dataInfo->genres, true);
            if (is_string($unpackGenres)) $unpackGenres = json_decode($unpackGenres, true);

            if (is_array($unpackGenres)) {
                $genresClean = array_values(array_unique(array_filter(array_map('trim', $unpackGenres))));
                $toTranslate = [];

                foreach ($genresClean as $tagNameEn) {
                    if (empty($tagNameEn)) continue;
                    if (preg_match('/[\p{Cyrillic}]/u', $tagNameEn)) continue;
                    $toTranslate[] = $tagNameEn;
                }

                if (!empty($toTranslate)) {
                    // 🔹 Для жанров: порог 70% + словарь
                    $translatedList = $this->translateLargeBatch($toTranslate, 0.7);

                    $finalGenres = [];
                    $transIdx = 0;
                    foreach ($genresClean as $g) {
                        if (preg_match('/[\p{Cyrillic}]/u', $g)) {
                            $finalGenres[] = ucfirst($g);
                        } else {
                            $t = $translatedList[$transIdx] ?? $g;
                            $cleaned = str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $t);
                            $finalGenres[] = ucfirst($this->fixFilmContext($g, $cleaned));
                            $transIdx++;
                        }
                    }

                    $resJson = json_encode($finalGenres, JSON_UNESCAPED_UNICODE);
                    if (json_validate($resJson)) $this->dataMovie['genres'] = $resJson;
                }
            }
        }

        // ==================== CAST ====================
        $this->dataMovie['cast'] = null;
        if (!empty($dataInfo->cast)) {
            $castDecode = json_decode($dataInfo->cast, true);
            if (is_array($castDecode)) {
                $resultCast = [];
                $actors = [];
                $roles = [];
                $actorMap = [];
                $roleMap = [];

                foreach ($castDecode as $id => $item) {
                    if (!isset($item['actor']) || !isset($item['role'])) continue;
                    $a = trim($item['actor']);
                    $r = trim($item['role']);
                    $resultCast[$id] = ['actor' => null, 'role' => null];

                    if (!preg_match('/[\p{Cyrillic}]/u', $a)) {
                        $actors[] = $a;
                        $actorMap[] = $id;
                    } else {
                        $resultCast[$id]['actor'] = $a;
                    }

                    if (!preg_match('/[\p{Cyrillic}]/u', $r)) {
                        $roles[] = $r;
                        $roleMap[] = $id;
                    } else {
                        $resultCast[$id]['role'] = $r;
                    }
                }

                if (!empty($actors)) {
                    $transActors = $this->translateLargeBatch($actors, 0.9);
                    foreach ($actorMap as $i => $id) {
                        $t = $transActors[$i] ?? $actors[$i];
                        $resultCast[$id]['actor'] = str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $t);
                    }
                }

                if (!empty($roles)) {
                    // 🔹 Для ролей: порог 70% + словарь
                    $transRoles = $this->translateLargeBatch($roles, 0.7);
                    foreach ($roleMap as $i => $id) {
                        $t = $transRoles[$i] ?? $roles[$i];
                        $cleaned = str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $t);
                        $resultCast[$id]['role'] = $this->fixFilmContext($roles[$i], $cleaned);
                    }
                }

                $resJson = json_encode($resultCast, JSON_UNESCAPED_UNICODE);
                if (json_validate($resJson)) $this->dataMovie['cast'] = $resJson;
            }
        }

        // ==================== DIRECTORS ====================
        if (!empty($dataInfo->directors) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->directors)) {
            $r = $this->startTranslate($dataInfo->directors);
            $cleaned = str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $r ?? $dataInfo->directors);
            $this->dataMovie['directors'] = json_validate($cleaned) ? $cleaned : $dataInfo->directors;
        }

        // ==================== WRITERS ====================
        if (!empty($dataInfo->writers) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->writers)) {
            $r = $this->startTranslate($dataInfo->writers);
            $cleaned = str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $r ?? $dataInfo->writers);
            if (!strpos($cleaned, '"')) {
                $cleaned = str_replace(['[', ']', ','], ['["', '"]', '","'], $cleaned);
                $cleaned = trim($cleaned);
            }
            $this->dataMovie['writers'] = json_validate($cleaned) ? $cleaned : $dataInfo->writers;
        }

        // ==================== STORY LINE ====================
        if (!empty($dataInfo->story_line) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->story_line)) {
            $text = $dataInfo->story_line;
            if (strlen($text) > 4000) $text = mb_strimwidth($text, 0, 4000, "...");
            $this->dataMovie['story_line'] = str_replace("\u{200B}", '', $this->startTranslate($text));
        }

        // ==================== COUNTRIES ====================
        if (!empty($dataInfo->countries) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->countries)) {
            $r = $this->startTranslate($dataInfo->countries);
            $cleaned = str_replace('».', '', str_replace(['«','»'], '"', str_replace("\u{200B}", '', $r ?? $dataInfo->countries)));
            $this->dataMovie['countries'] = json_validate($cleaned) ? $cleaned : null;
        }

        // ==================== RELEASE DATE ====================
        if (!empty($dataInfo->release_date)) {
            $rd = $dataInfo->release_date;
            if (str_contains($rd, ",") && !preg_match('/[\p{Cyrillic}]/u', $rd)) {
                $this->dataMovie['release_date'] = $this->startTranslate($rd);
            }
        }

        $this->updateOrCreateLocalizing($this->dataMovie);
        $this->dataMovie = [];
    }

    public function translateCeleb($dataInfo, $celebId, $columnKey) {
        $this->columnId = $celebId;
        $this->columnKey = $columnKey;
        $this->dataCeleb['id_celeb'] = $dataInfo->id_celeb;
        $this->localizingTable = new CelebsInfoRu();

        $validSections = ['actor', 'actress', 'producer', 'writer', 'director', 'composer', 'soundtrack'];

        if (!empty($dataInfo->filmography)) {
            $filmographyDecode = json_decode($dataInfo->filmography, true);
            if (is_array($filmographyDecode)) {
                $resultFilmography = [];
                $allRoles = [];
                $allTitles = [];
                $mapRoles = []; // [globalIndex => [section, id]]
                $mapTitles = [];

                // 1. Сбор данных
                foreach ($filmographyDecode as $keySection => $valSection) {
                    if (!in_array($keySection, $validSections, true)) continue;

                    foreach ($valSection as $keyId => $item) {
                        $resultFilmography[$keySection][$keyId] = ['role' => null, 'year' => null, 'title' => null];

                        preg_match('/\d\d\d\d/', $item['year'] ?? '', $y);
                        $resultFilmography[$keySection][$keyId]['year'] = $y[0] ?? null;

                        $role = trim($item['role'] ?? '');
                        if (!empty($role) && !preg_match('/[\p{Cyrillic}]/u', $role)) {
                            $allRoles[] = $role;
                            $mapRoles[] = ['s' => $keySection, 'i' => $keyId];
                        } elseif (!empty($role)) {
                            $resultFilmography[$keySection][$keyId]['role'] = $role;
                        }

                        $title = trim($item['title'] ?? '');
                        if (!empty($title) && !preg_match('/[\p{Cyrillic}]/u', $title)) {
                            $allTitles[] = $title;
                            $mapTitles[] = ['s' => $keySection, 'i' => $keyId];
                        } elseif (!empty($title)) {
                            $resultFilmography[$keySection][$keyId]['title'] = ucfirst($title);
                        }
                    }
                }

                // 2. Массовый перевод Ролей
                if (!empty($allRoles)) {
                    $translated = $this->translateLargeBatch($allRoles);
                    foreach ($mapRoles as $idx => $pos) {
                        $t = $translated[$idx] ?? '';
                        $cleaned = str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $t);
                        $originalRole = $allRoles[$idx] ?? ''; // сохраняем оригинал для контекста
                        $resultFilmography[$pos['s']][$pos['i']]['role'] = $this->fixFilmContext($originalRole, $cleaned);
                    }
                }

                // 3. Массовый перевод Названий
                if (!empty($allTitles)) {
                    $translated = $this->translateLargeBatch($allTitles);
                    foreach ($mapTitles as $idx => $pos) {
                        $t = $translated[$idx] ?? '';
                        $resultFilmography[$pos['s']][$pos['i']]['title'] = ucfirst(str_replace(["\u{200B}", '«', '»'], ['', '"', '"'], $t));
                    }
                }

                $this->dataCeleb['filmography'] = json_encode($resultFilmography, JSON_UNESCAPED_UNICODE);
            }
        }

        // Остальные поля
        if (!empty($dataInfo->nameActor) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->nameActor)) {
            $this->dataCeleb['nameActor'] = $this->startTranslate($dataInfo->nameActor);
        }
        if (!empty($dataInfo->birthdayLocation) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->birthdayLocation)) {
            $this->dataCeleb['birthdayLocation'] = $this->startTranslate($dataInfo->birthdayLocation);
        }
        if (!empty($dataInfo->dieLocation) && !preg_match('/[\p{Cyrillic}]/u', $dataInfo->dieLocation)) {
            $this->dataCeleb['dieLocation'] = $this->startTranslate($dataInfo->dieLocation);
        }

        $this->updateOrCreateLocalizing($this->dataCeleb);
        $this->dataCeleb = [];
    }

    /**
     * Главный метод батчинга. Делит массив на части так, чтобы итоговая строка запроса не превышала 4000 символов.
     */
    private function translateLargeBatch(array $texts, float $minSuccessRate = 0.9): array {
        if (empty($texts)) return [];

        $results = [];
        $currentChunk = [];
        $currentLen = 0;
        $limit = 2000; // Безопасный лимит для payload

        foreach ($texts as $text) {
            // Рассчитываем длину с учетом обертки и разделителей
            $itemLen = mb_strlen($text, 'UTF-8') + 30;

            if (!empty($currentChunk) && ($currentLen + $itemLen > $limit)) {
                $translatedPart = $this->processBatchChunk($currentChunk, $minSuccessRate);
                $results = array_merge($results, $translatedPart);
                $currentChunk = [];
                $currentLen = 0;
            }

            $currentChunk[] = $text;
            $currentLen += $itemLen;
        }

        // Отправляем последний остаток
        if (!empty($currentChunk)) {
            $translatedPart = $this->processBatchChunk($currentChunk, $minSuccessRate);
            $results = array_merge($results, $translatedPart);
        }

        return $results;
    }
    private function processBatchChunk(array $chunk, float $minSuccessRate = 0.9): array {
        if (empty($chunk)) return [];

        // Один элемент — переводим напрямую
        if (count($chunk) === 1) {
            return [$this->startTranslate($chunk[0])];
        }

        // 🔹 PUA-маркеры: гарантированно не переводятся
        $MARK_START = "\u{E001}";
        $MARK_SEP = "\u{E002}";
        $MARK_END = "\u{E003}";
        $DELIM = '§';

        // Формируем payload: ▶0●Text◀ § ▶1●Text◀
        $wrapped = [];
        foreach ($chunk as $idx => $text) {
            $wrapped[] = "{$MARK_START}{$idx}{$MARK_SEP}{$text}{$MARK_END}";
        }
        $payload = implode(" {$DELIM} ", $wrapped);

        // 🔹 Безопасный лимит: 2000 символов
        $MAX_PAYLOAD = 2000;
        if (mb_strlen($payload, 'UTF-8') > $MAX_PAYLOAD) {
            $mid = intdiv(count($chunk), 2);
            return array_merge(
                $this->processBatchChunk(array_slice($chunk, 0, $mid), $minSuccessRate),
                $this->processBatchChunk(array_slice($chunk, $mid), $minSuccessRate)
            );
        }

        try {
            $translatedPayload = $this->startTranslate($payload);

            if (!$translatedPayload) {
                throw new \Exception("Empty response");
            }

            // Чистим только нулевые символы
            $cleaned = str_replace("\u{200B}", '', $translatedPayload);

            // Парсим по PUA-маркерам
            $pattern = '/' . preg_quote($MARK_START, '/') . '(\d+)' .
                preg_quote($MARK_SEP, '/') . '(.*?)' .
                preg_quote($MARK_END, '/') . '/us';

            preg_match_all($pattern, $cleaned, $matches, PREG_SET_ORDER);

            $batchMap = [];
            foreach ($matches as $m) {
                $batchMap[(int)$m[1]] = trim($m[2]);
            }

            // 🔹 Гибкая проверка успеха
            $successRate = count($batchMap) / count($chunk);
            if ($successRate < $minSuccessRate) {
                throw new \Exception("Low parse rate: " . round($successRate * 100) . "%");
            }

            // Восстанавливаем порядок + применяем словарь
            $finalResult = [];
            for ($i = 0; $i < count($chunk); $i++) {
                $original = $chunk[$i] ?? '';
                $translated = $batchMap[$i] ?? $original;
                $finalResult[] = $this->fixFilmContext($original, $translated);
            }

            return $finalResult;

        } catch (\Exception $e) {
            Log::warning("Batch chunk failed, fallback to individual", [
                'error' => $e->getMessage(),
                'chunk_size' => count($chunk)
            ]);

            $fallbackResults = [];
            foreach ($chunk as $text) {
                $fallbackResults[] = $this->startTranslate($text);
            }
            return $fallbackResults;
        }
    }
    /**
     * Корректирует перевод с учётом кино-контекста
     */
    private function fixFilmContext(string $original, string $translated): string {
        $key = strtolower(trim($original));

        // Точное совпадение
        if (isset($this->filmRoleDictionary[$key])) {
            return $this->filmRoleDictionary[$key];
        }

        // Частичное совпадение (например "Director (uncredited)")
        foreach ($this->filmRoleDictionary as $dictKey => $dictValue) {
            if (stripos($key, $dictKey) !== false) {
                $pattern = '/\b' . preg_quote($dictKey, '/') . '\b/iu';
                return preg_replace($pattern, $dictValue, $translated);
            }
        }

        return $translated;
    }
}
