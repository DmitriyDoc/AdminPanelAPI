<?php

namespace App\Traits\Components;

use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait CelebsCreditsTrait
{
    protected function credits($pages)
    {
        if (empty($pages)) return;

        foreach ($pages as $url => $page) {
            $url = trim($url);
            if (empty(trim($page))) continue;

            $document = new Document(trim($page));
            $this->logErrors($document, "div.error_code_404", "404-->>", $url);
            $this->logErrors($document, "div.errorPage__container", "500-->>", $url);

            if (!$document->has("div[data-testid='Filmography']") && !$document->has("div[data-testid='filmography']")) {
                Log::info("Filmography block not found: {$url}");
                continue;
            }

            $actorId = get_id_from_url($url, self::ACTOR_PATTERN);
            if (empty($actorId)) {
                Log::error("Failed to extract actor ID from: {$url}");
                continue;
            }

            $insertData = [$this->signByField => $actorId];
            $filmographyData = [];
            $sectionHeaders = $document->find('div[class*="filmo-section-"] h3.ipc-title__text');

            foreach ($sectionHeaders as $header) {
                $rawHeaderText = trim($header->text());
                $occupationLabel = preg_replace('/\s*\(\d+\)\s*$/', '', $rawHeaderText);
                $occupationKey = strtolower($occupationLabel);

                if (!in_array($occupationKey, ['actor','actress','producer','writer','soundtrack','director','composer'])) {
                    continue;
                }

                $sectionDiv = $header->closest('div[class*="filmo-section-"]');
                if (!$sectionDiv) continue;

                // Берём следующий sibling — в нём могут быть один или несколько div с ul
                $nextSibling = $sectionDiv->nextSibling();
                if (!$nextSibling) continue;

                $allItems = [];

                // Сначала попробуем найти все <ul> напрямую внутри nextSibling
                $directUls = $nextSibling->find('ul.ipc-metadata-list');

                if (!empty($directUls)) {
                    // Если нашли ul на верхнем уровне — используем их
                    foreach ($directUls as $ul) {
                        $allItems = array_merge($allItems, $ul->find('li.ipc-metadata-list-summary-item'));
                    }
                } else {
                    // Иначе ищем все вложенные div и в каждом — ul
                    $nestedDivs = $nextSibling->find('div');
                    if (!empty($nestedDivs)) {
                        foreach ($nestedDivs as $div) {
                            $ul = $div->first('ul.ipc-metadata-list');
                            if ($ul) {
                                $allItems = array_merge($allItems, $ul->find('li.ipc-metadata-list-summary-item'));
                            }
                        }
                    }
                }

                if (empty($allItems)) continue;

                foreach ($allItems as $li) {
                    $titleLink = $li->first('a.ipc-metadata-list-summary-item__t');
                    if (!$titleLink || !$titleLink->hasAttribute('href')) continue;

                    $movieUrl = $titleLink->attr('href');
                    $movieID = get_id_from_url($movieUrl, self::ID_PATTERN);
                    if (empty($movieID)) {
                        Log::debug("Movie ID not extracted from: {$movieUrl}");
                        continue;
                    }

                    $title = trim($titleLink->text() ?? '');
                    $year = null;
                    $role = null;

                    // Год — в блоке с классом ...__ctl
                    $yearEl = $li->first('ul.ipc-inline-list--inline.ipc-metadata-list-summary-item__ctl span.ipc-metadata-list-summary-item__li');
                    if ($yearEl) {
                        $raw = trim(html_entity_decode(str_replace('&nbsp;', ' ', $yearEl->text() ?? '')));
                        if (preg_match('/(\d{4})/', $raw, $m)) {
                            $year = $m[1];
                        }
                    }

                    // Роль — в блоке с data-testid="credit-roles-list"
                    $roleEl = $li->first('ul[data-testid="credit-roles-list"] li');
                    if ($roleEl) {
                        $r = trim($roleEl->text());
                        if ($occupationKey === 'soundtrack') {
                            if (!preg_match('/(performer|music|song|written by|lyrics|singer)/i', $r)) {
                                continue;
                            }
                        } else {
                            if (in_array(strtolower($r), ['tv series', 'short', 'video', ''], true)) {
                                $r = null;
                            }
                        }
                        $role = $r;
                    }

                    $filmographyData[$occupationKey][$movieID] = [
                        'title' => $title,
                        'year'  => $year,
                        'role'  => $role
                    ];
                }
            }

            if (empty($filmographyData)) {
                Log::warning("No filmography data extracted for {$actorId} ({$url})");
                continue;
            }

            $currentJson = $this->selectDB('localizing_celebs_info_en', $actorId, $this->signByField, 'filmography');
            $existing = [];
            if (!empty($currentJson[0])) {
                $decoded = json_decode($currentJson[0], true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                } else {
                    Log::warning("Invalid JSON in filmography for {$actorId}, resetting.");
                }
            }

            // Объединяем существующие и новые данные
            $merged = $existing;
            foreach ($filmographyData as $occupation => $newMovies) {
                if (!is_array($newMovies)) continue;

                if (!isset($merged[$occupation])) {
                    $merged[$occupation] = [];
                }

                // Добавляем/обновляем фильмы по movieID
                $merged[$occupation] = array_merge($merged[$occupation], $newMovies);
            }

            // Кодируем и сохраняем
            $filmographyJson = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($filmographyJson === false) {
                Log::error("Failed to encode filmography JSON for {$actorId}");
                continue;
            }

            // Критическая проверка: не пустой ли результат?
            if ($filmographyJson === '{}') {
                Log::warning("Merged filmography is empty for {$actorId}");
                continue;
            }

            $insertData['filmography'] = $filmographyJson;

            $this->updateOrInsert('localizing_celebs_info_en', $insertData, $this->signByField);
            Log::info("Updated filmography for {$actorId}, occupations: " . implode(', ', array_keys($filmographyData)));
        }
    }
}
