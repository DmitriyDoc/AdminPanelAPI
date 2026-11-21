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

            // Проверка на ошибки загрузки
            $this->logErrors($document, "div.error_code_404", "404-->>", $url);
            $this->logErrors($document, "div.errorPage__container", "500-->>", $url);

            // Проверяем наличие блока фильмографии
            if (!$document->has("div.ipc-accordion")) {
                Log::info("Filmography accordion block not found: {$url}");
                continue;
            }

            $actorId = get_id_from_url($url, self::ACTOR_PATTERN);
            if (empty($actorId)) {
                Log::error("Failed to extract actor ID from: {$url}");
                continue;
            }

            $insertData = [$this->signByField => $actorId];
            $filmographyData = [];

            // 🔧 НОВОЕ: Находим ВСЕ аккордеоны на странице (и Upcoming, и Previous)
            $allAccordions = $document->find('div.ipc-accordion');

            if (empty($allAccordions)) {
                Log::warning("No accordions found for {$actorId} ({$url})");
                continue;
            }

            Log::info("🎭 Found accordions", [
                'actor_id' => $actorId,
                'count' => count($allAccordions),
            ]);

            foreach ($allAccordions as $accordion) {
                // 🔧 Находим категорию этого аккордеона (обратный поиск)
                $category = $this->findAccordionCategory($accordion);

                if (!$category) {
                    Log::debug("⚠️ Could not determine category for accordion");
                    continue;
                }

                $occupationKey = strtolower(trim($category));

                // Фильтруем только нужные нам категории
                if (!in_array($occupationKey, ['actor', 'actress', 'producer', 'writer', 'soundtrack', 'director', 'composer', 'self', 'archive footage'])) {
                    continue;
                }

                // Парсим элементы внутри аккордеона
                $lists = $accordion->find('ul.ipc-metadata-list');
                if (empty($lists)) continue;

                foreach ($lists as $list) {
                    $items = $list->find('li.ipc-metadata-list-summary-item');
                    foreach ($items as $li) {
                        $this->parseCreditItem($li, $occupationKey, $filmographyData);
                    }
                }
            }

            if (empty($filmographyData)) {
                Log::warning("No filmography data extracted for {$actorId} ({$url})");
                continue;
            }

            // --- Логика сохранения (без изменений) ---
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

            $merged = $existing;
            foreach ($filmographyData as $occupation => $newMovies) {
                if (!is_array($newMovies)) continue;
                if (!isset($merged[$occupation])) {
                    $merged[$occupation] = [];
                }
                $merged[$occupation] = array_merge($merged[$occupation], $newMovies);
            }

            $filmographyJson = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($filmographyJson === false || $filmographyJson === '{}') {
                Log::warning("Failed to encode or empty filmography JSON for {$actorId}");
                continue;
            }

            $insertData['filmography'] = $filmographyJson;
            $this->updateOrInsert('localizing_celebs_info_en', $insertData, $this->signByField);
            Log::info("✅ Updated filmography for {$actorId}, occupations: " . implode(', ', array_keys($filmographyData)));
        }
    }

    /**
     * 🔧 НОВОЕ: Находим категорию аккордеона, поднимаясь вверх по DOM
     * и ища ближайший предыдущий заголовок h4.ipc-title__text
     *
     * Работает для обоих случаев:
     * - Много фильмов (Upcoming + Previous аккордеоны)
     * - Один фильм (только Previous аккордеон)
     */
    private function findAccordionCategory($accordion)
    {
        // Идём вверх по DOM от аккордеона
        $current = $accordion;
        $maxDepth = 15; // Защита от бесконечного цикла

        while ($current && $maxDepth-- > 0) {
            // Получаем предыдущий элементный sibling (пропуская текстовые ноды)
            $prev = $this->getPreviousElementSibling($current);

            while ($prev) {
                // Проверяем, есть ли в этом sibling заголовок категории
                try {
                    $header = $prev->first('h4.ipc-title__text');
                    if ($header) {
                        return trim($header->text());
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибки
                }

                $prev = $this->getPreviousElementSibling($prev);
            }

            // Поднимаемся на уровень выше
            try {
                $current = $current->parent();
            } catch (\Exception $e) {
                break;
            }
        }

        return null;
    }

    /**
     * 🔧 НОВОЕ: Получаем предыдущий элементный sibling
     * Пропускает текстовые ноды (пробелы, переносы строк между div'ами)
     */
    private function getPreviousElementSibling($node)
    {
        if (!$node) return null;

        try {
            $sibling = $node->previousSibling();
        } catch (\Exception $e) {
            return null;
        }

        $maxAttempts = 20; // Защита от бесконечного цикла

        while ($sibling && $maxAttempts-- > 0) {
            // Проверяем, является ли узел элементом (а не текстовой нодой)
            // У элементов DiDom есть метод has(), у текстовых нод — нет
            try {
                // Пытаемся вызвать метод, который есть только у элементов
                if (method_exists($sibling, 'has') && method_exists($sibling, 'first')) {
                    // Дополнительная проверка: nodeName должен быть именем тега (div, section и т.д.)
                    // а не '#text' (текстовая нода)
                    if (isset($sibling->nodeName) && $sibling->nodeName !== '#text' && $sibling->nodeName !== '#comment') {
                        return $sibling;
                    }
                    // Если nodeName не доступен, пробуем другой способ
                    if (!isset($sibling->nodeName)) {
                        return $sibling;
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем
            }

            try {
                $sibling = $sibling->previousSibling();
            } catch (\Exception $e) {
                break;
            }
        }

        return null;
    }

    /**
     * Вспомогательный метод для парсинга одного элемента списка (li)
     */
    private function parseCreditItem($li, $occupationKey, &$filmographyData)
    {
        $titleLink = $li->first('a.ipc-metadata-list-summary-item__t');
        if (!$titleLink || !$titleLink->hasAttribute('href')) return;

        $movieUrl = $titleLink->attr('href');
        $movieID = get_id_from_url($movieUrl, self::ID_PATTERN);

        if (empty($movieID)) {
            return;
        }

        $title = trim($titleLink->text() ?? '');

        // Поиск года
        $year = null;
        $yearList = $li->first('ul.ipc-metadata-list-summary-item__ctl');
        if ($yearList) {
            $yearSpan = $yearList->first('span.ipc-metadata-list-summary-item__li');
            if ($yearSpan) {
                $rawYear = $yearSpan->text();
                if (preg_match('/(\d{4})/', $rawYear, $matches)) {
                    $year = $matches[1];
                }
            }
        }

        // Поиск роли
        $role = null;
        $roleList = $li->first('ul[data-testid="credit-roles-list"]');
        if ($roleList) {
            $roleItem = $roleList->first('li');
            if ($roleItem) {
                $r = trim($roleItem->text());

                if ($occupationKey === 'soundtrack') {
                    if (!preg_match('/(performer|music|song|written by|lyrics|singer)/i', $r)) {
                        return;
                    }
                }
                elseif (in_array(strtolower($r), ['tv series', 'short', 'video', 'podcast series', 'tv movie', ''], true)) {
                    $role = null;
                } else {
                    $role = $r;
                }
            }
        }

        if (!isset($filmographyData[$occupationKey])) {
            $filmographyData[$occupationKey] = [];
        }

        $filmographyData[$occupationKey][$movieID] = [
            'title' => $title,
            'year'  => $year,
            'role'  => $role
        ];
    }
}
