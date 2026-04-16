<?php

namespace App\Traits\Components;

use App\Http\Controllers\TranslatorController;
use App\Models\AssignPoster;
use App\Models\Tag;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait MoviesInfoTrait
{
    protected function getMoviesInfo($pages, $pattern = null)
    {
        if (empty($pages)) {
            Log::warning("No pages received for processing in getMoviesInfo");
            return;
        }

        Log::info("🚀 Starting parsing of " . count($pages) . " pages in getMoviesInfo...");

        foreach ($pages as $url => $page) {
            if (empty($page) || !is_string($page) || trim($page) === '') {
                Log::error("Empty page: $url");
                continue;
            }

            // 🔥 Логика повторных попыток для основной информации
            $maxAttempts = 3;
            $attempt = 0;
            $success = false;

            while ($attempt < $maxAttempts && !$success) {
                $attempt++;
                Log::debug("🔄 Attempt #$attempt for $url");

                try {
                    $document = new \DiDom\Document(trim($page));

                    // Проверка на ошибки 404/500
                    if ($document->has('div[class=error_code_404]') || $document->has('div[class=errorPage__container]')) {
                        Log::error("Error page detected: $url");
                        // Если это не первая попытка — ждём перед следующей
                        if ($attempt < $maxAttempts) {
                            $delay = rand(3, 5);
                            Log::debug("😴 Waiting {$delay}s before retry...");
                            sleep($delay);
                        }
                        continue;
                    }

                    // ID из URL
                    $idFromUrl = get_id_from_url($url, self::ID_PATTERN) ?? null;
                    if (!$idFromUrl) {
                        Log::error("❌ Could not extract ID from URL: $url");
                        break; // Не имеет смысла повторять, если не можем извлечь ID
                    }

                    $insertData = [];
                    $insertData[$this->update_info_table]['id_movie'] = $idFromUrl;
                    $insertData[$this->update_en_info_table]['id_movie'] = $idFromUrl;

                    // 🔍 Проверка наличия заголовка (критичный элемент)
                    if (!$document->has('h1')) {
                        Log::warning("⚠️ H1 not found on attempt #$attempt for $url");
                        if ($attempt < $maxAttempts) {
                            $delay = rand(3, 5);
                            Log::debug("😴 Waiting {$delay}s before retry...");
                            sleep($delay);
                            continue;
                        }
                        Log::error("❌ CRITICAL: <h1> tag NOT found after $maxAttempts attempts for $url");
                        break;
                    }

                    $signByField = $this->signByField;
                    $translator = new \App\Http\Controllers\TranslatorController();

                    // Проверка, есть ли уже описание (чтобы не перезаписывать)
                    $descriptionExist = \Illuminate\Support\Facades\DB::table($this->update_en_info_table)
                        ->where('id_movie', $idFromUrl)
                        ->where(function ($query) {
                            $query->whereNotNull('story_line')
                                ->where('story_line', '!=', '');
                        })->exists();

                    // TITLE
                    $title = $document->find('h1');
                    if (!$this->isUpdate){
                        $titleRus = $translator->translateSingle($title[0]->text());
                        $insertData[$this->update_info_table]['title'] = $titleRus ?? null;
                    }


                    // ORIGINAL TITLE
                    $originalTitle = $title[0]->nextSibling('div') ?? null;
                    if ($originalTitle) {
                        $text = trim($originalTitle->text());
                        if (strpos($text, ':') !== false) {
                            $originalTitle = trim(explode(':', $text, 2)[1]);
                        } else {
                            $originalTitle = $text;
                        }
                        $originalTitle = html_entity_decode($originalTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    $insertData[$this->update_info_table]['original_title'] = $originalTitle ?? $title[0]->text() ?? null;

                    // TYPE, YEAR, RESTRICTIONS, RUNTIME
                    $infoContainer = $title[0]->nextSibling('ul') ?? null;
                    $foundTypeName = null;

                    if ($infoContainer) {
                        $runtimePattern     = '/^(\d+h?\s*(\d+m)?|\d+m)$/i';
                        $restrictionPattern = '/^(PG-13|R|TV-MA|16\+|18\+|0\+|G|PG|TV-14|TV-PG|Not Rated|Unrated)$/i';
                        $typeFilmMap = [
                            2 => 'Mini Series', 3 => 'Short Film', 4 => 'TV Movie',
                            5 => 'TV Series', 6 => 'TV Short', 7 => 'TV Special', 8 => 'Video',
                        ];

                        foreach ($infoContainer->find('li') as $li) {
                            $text = trim($li->text());

                            // Year
                            if (preg_match('/(\d{4})/', $text, $matches)) {
                                $year = (int)$matches[1];
                                if ($year >= 1900 && $year <= date('Y') + 5) {
                                    $insertData[$this->update_info_table]['year_release'] = $year;
                                    continue;
                                }
                            }
                            // Runtime
                            if (preg_match($runtimePattern, $text)) {
                                $minutes = 0;
                                if (preg_match('/(\d+)h/i', $text, $h)) $minutes += (int)$h[1] * 60;
                                if (preg_match('/(\d+)m/i', $text, $m)) $minutes += (int)$m[1];
                                $insertData[$this->update_info_table]['runtime'] = $minutes > 0 ? $minutes . ' min' : $text;
                                continue;
                            }
                            // Restriction
                            if (preg_match($restrictionPattern, $text)) {
                                $insertData[$this->update_info_table]['restrictions'] = $text;
                                continue;
                            }
                            // Type
                            $normalizedText = preg_replace('/[\s–—-]+/', ' ', strtolower($text));
                            foreach ($typeFilmMap as $typeName) {
                                $normalizedName = preg_replace('/[\s–—-]+/', ' ', strtolower($typeName));
                                if (stripos($normalizedText, $normalizedName) !== false) {
                                    $foundTypeName = $typeName;
                                    break;
                                }
                            }
                        }
                    }

                    // RATING
                    if ($document->has("div[data-testid=hero-rating-bar__aggregate-rating__score]")) {
                        $ratingContainer = $document->first("div[data-testid=hero-rating-bar__aggregate-rating__score]");
                        $ratingSpan = $ratingContainer->first('span');
                        if ($ratingSpan) {
                            $insertData[$this->update_info_table]['rating'] = (float)str_replace(',', '.', $ratingSpan->text());
                        }
                    }

                    // GENRES
                    if ($document->has(".ipc-chip-list__scroller a")) {
                        $genresContainer = $document->find(".ipc-chip-list__scroller a");
                        $genresArray = [];
                        foreach ($genresContainer as $item) {
                            $genresArray[] = $item->text();
                        }
                        $insertData[$this->update_en_info_table]['genres'] = json_encode($genresArray, JSON_UNESCAPED_UNICODE);
                    }

                    // CAST, DIRECTORS, WRITERS
                    if ($document->has("section[data-testid=title-cast]")) {
                        $castContainer = $document->first('section[data-testid=title-cast]');

                        if ($castContainer->has('div[data-testid=shoveler-items-container]')) {
                            $castArray = [];
                            $shoveler = $castContainer->find('div[data-testid=shoveler-items-container]');
                            if (!empty($shoveler)) {
                                foreach ($shoveler[0]->children() as $item) {
                                    if (!$item->lastChild()) continue;
                                    $lastChild = $item->lastChild();

                                    $role = '';
                                    $charLink = $lastChild->find('div a[data-testid=cast-item-characters-link]');
                                    if (!empty($charLink)) {
                                        $charList = $lastChild->find('div ul[data-testid=cast-item-characters-list]');
                                        if (!empty($charList)) {
                                            $role = trim($charList[0]->text(), '…');
                                        }
                                    }

                                    $actorLinks = $lastChild->find('a');
                                    if (!empty($actorLinks)) {
                                        $actorHref = $actorLinks[0]->getAttribute('href');
                                        $actorName = $actorLinks[0]->text();
                                        $actorId = get_id_from_url($actorHref, self::ACTOR_PATTERN) ?? '';

                                        if ($actorId && $actorName) {
                                            $castArray[$actorId] = ['actor' => $actorName, 'role' => $role];
                                        }
                                    }
                                }
                                $insertData[$this->update_en_info_table]['cast'] = json_encode($castArray, JSON_UNESCAPED_UNICODE);
                            }
                        }

                        $crewContainer = $castContainer->lastChild();
                        if ($crewContainer && count($crewContainer->children()) > 2) {
                            $directorsArray = [];
                            $writersArray = [];

                            foreach ($crewContainer->children() as $item) {
                                $labelNode = $item->child(0);
                                if (!$labelNode) continue;
                                $label = $labelNode->text();

                                $creditsNode = $item->child(1);
                                if (!$creditsNode) continue;

                                $ulList = $creditsNode->find('ul');
                                if (empty($ulList)) continue;
                                $ul = $ulList[0];

                                if (in_array($label, ['Director', 'Directors'])) {
                                    foreach ($ul->children() as $el) {
                                        $a = $el->find('a');
                                        if (!empty($a)) {
                                            $aId = get_id_from_url($a[0]->getAttribute('href'), self::ACTOR_PATTERN);
                                            $aName = $a[0]->text();
                                            if ($aId && $aName) $directorsArray[$aId] = $aName;
                                        }
                                    }
                                }

                                if (in_array($label, ['Writer', 'Writers', 'Creators'])) {
                                    foreach ($ul->children() as $el) {
                                        $a = $el->find('a');
                                        if (!empty($a)) {
                                            $aId = get_id_from_url($a[0]->getAttribute('href'), self::ACTOR_PATTERN);
                                            $aName = $a[0]->text();
                                            if ($aId && $aName) $writersArray[$aId] = $aName;
                                        }
                                    }
                                }
                            }

                            if (!empty($directorsArray)) $insertData[$this->update_en_info_table]['directors'] = json_encode($directorsArray, JSON_UNESCAPED_UNICODE);
                            if (!empty($writersArray)) $insertData[$this->update_en_info_table]['writers'] = json_encode($writersArray, JSON_UNESCAPED_UNICODE);
                        }
                    }

                    // STORY LINE
                    if (!$descriptionExist && $document->has("span[data-testid=plot-l]")) {
                        $storyContainer = $document->first("span[data-testid=plot-l]");
                        if ($storyContainer) {
                            $insertData[$this->update_en_info_table]['story_line'] = rtrim($storyContainer->text(), 'Read all');
                        }
                    }

                    // RELEASE DATE, COUNTRIES, COMPANIES, BUDGET (оставляем базовую логику)
                    if ($document->has("li[data-testid=title-details-releasedate]")) {
                        $rd = $document->first('li[data-testid=title-details-releasedate]')->find('ul li a');
                        if (!empty($rd)) $insertData[$this->update_en_info_table]['release_date'] = $rd[0]->text();
                    }

                    if ($document->has("li[data-testid=title-details-origin]")) {
                        $countriesArray = [];
                        $originUl = $document->first('li[data-testid=title-details-origin]')->find('ul');
                        if (!empty($originUl)) {
                            foreach ($originUl[0]->children() as $item) {
                                $lnk = $item->find('a');
                                if (!empty($lnk)) $countriesArray[] = $lnk[0]->text();
                            }
                        }
                        $insertData[$this->update_en_info_table]['countries'] = json_encode($countriesArray, JSON_UNESCAPED_UNICODE);
                    }

                    if ($document->has("li[data-testid=title-details-companies]")) {
                        $companiesArray = ['companies' => []];
                        $compUl = $document->first('li[data-testid=title-details-companies]')->find('ul');
                        if (!empty($compUl)) {
                            foreach ($compUl[0]->children() as $item) {
                                $companiesArray['companies'][] = $item->text();
                            }
                        }
                        $insertData[$this->update_info_table]['companies'] = serialize($companiesArray);
                    }

                    if ($document->has("li[data-testid=title-boxoffice-budget]")) {
                        $budgetText = $document->first("li[data-testid=title-boxoffice-budget]")->lastChild()->text();
                        $insertData[$this->update_info_table]['budget'] = get_id_from_url($budgetText, self::BUDGET_PATTERN);
                    }

                    // TYPE FILM
                    $rawTypeName = empty($foundTypeName) ? 'Feature Film' : $foundTypeName;
                    $normalizedKey = str_replace(' ', '', ucwords(strtolower(trim($rawTypeName))));
                    $typeMap = [
                        'FeatureFilm' => 1, 'MiniSeries' => 2, 'ShortFilm' => 3,
                        'TvMovie' => 4, 'TvSeries' => 5, 'TvShort' => 6,
                        'TvSpecial' => 7, 'Video' => 8,
                    ];
                    $typeId = $typeMap[$normalizedKey] ?? 1;
                    $insertData[$this->update_info_table]['type_film'] = (int) $typeId;
                    $insertData[$this->update_info_table]['published'] = 0;

                    transaction(function () use ($insertData, $signByField) {
                        if (!empty($insertData)) {
                            foreach ($insertData as $table => $data) {
                                if (method_exists($this, 'updateOrInsert')) {
                                    $this->updateOrInsert($table, $data, $signByField);
                                }
                            }
                        }
                    });

                    // Cleanup: удаляем старые постеры и очищаем кэш медиа
                    \App\Models\AssignPoster::query()->where('id_movie', $idFromUrl)->delete();
                    $hasher = new \App\Services\IdHasher($idFromUrl);
                    $apiService = new \App\Services\ApiRequestImages();
                    $apiService->clearImages($typeId, $hasher->getResult());

                    $success = true; // Выходим из цикла попыток
                    Log::info("✅ Successfully parsed $url on attempt #$attempt");

                } catch (\Exception $e) {
                    Log::error("💥 Exception on attempt #$attempt for $url: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);

                    if ($attempt < $maxAttempts) {
                        $delay = rand(3, 5);
                        Log::debug("😴 Waiting {$delay}s before retry #$attempt...");
                        sleep($delay);
                    } else {
                        Log::error("❌ Failed to parse $url after $maxAttempts attempts");
                    }
                }
            }
        }

        Log::info("🏁 Parsing batch finished in getMoviesInfo.");
    }
}
