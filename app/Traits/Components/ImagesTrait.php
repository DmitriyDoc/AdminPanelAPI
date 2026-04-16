<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait ImagesTrait
{
    /**
     * Парсит изображения из страниц mediaviewer с поддержкой повторных попыток
     *
     * @param array $linksArray Массив [movie_id => [url1, url2, ...]]
     * @param string $updateTable Имя таблицы для вставки
     * @return void
     */
    protected function getImages($linksArray, $updateTable)
    {
        Log::info("🚀 Starting getImages (with retries) for table: $updateTable", ['movies' => count($linksArray)]);

        foreach ($linksArray as $id => $links) {
            Log::debug("🎬 Processing Movie ID: $id, Links count: " . count($links));

            $this->deleteById($updateTable, $this->signByField, $id);

            // Обрабатываем ссылки пачками по 10
            foreach (array_chunk($links, 20) as $chunkIndex => $chunk) {
                // Небольшая пауза между чанками
                usleep(rand(100000, 300000));

                $connector = new \App\Http\Controllers\Parser\CurlConnectorController();

                // 🔥 Пытаемся получить страницы (основная попытка)
                $pages = $connector->getCurlImages($chunk);

                if (!is_array($pages) || empty($pages)) {
                    Log::warning("⚠️ Empty pages returned from connector for chunk");
                    continue;
                }

                foreach ($pages as $link => $page) {
                    // 🔥 Логика повторных попыток для коротких/битых страниц
                    $attempts = 0;
                    $maxAttempts = 3;
                    $processed = false;

                    while ($attempts < $maxAttempts && !$processed) {
                        $attempts++;

                        // Если первая попытка — используем то, что пришло
                        $currentPage = $page;

                        // Если это повторная попытка и страница короткая — запрашиваем заново
                        if ($attempts > 1 && (!is_string($currentPage) || strlen(trim($currentPage)) < 20000)) {
                            Log::debug("🔄 Retry #$attempts for $link");
                            $retryResult = $connector->getCurlImages([$link]);
                            $currentPage = $retryResult[$link] ?? null;

                            // Пауза между попытками (1-2 секунды)
                            if ($attempts < $maxAttempts) {
                                usleep(rand(1000000, 2000000));
                            }
                        }

                        // Проверка контента
                        if (!is_string($currentPage) || empty(trim($currentPage))) {
                            Log::debug("⚠️ Attempt #$attempts: Empty/invalid content for $link");
                            continue;
                        }

                        try {
                            $document = new \DiDom\Document(trim($currentPage));

                            // Проверка на ошибки 404/500
                            if ($this->logError($document, "div[class=error_code_404]", "404", $link)) continue;
                            if ($this->logError($document, "div[class=errorPage__container]", "500", $link)) continue;

                            // Проверка наличия медиа-вьюера
                            if (!$document->has('div[data-testid=media-viewer]')) {
                                if ($attempts < $maxAttempts) {
                                    Log::debug("⚠️ Attempt #$attempts: No viewer found for $link, will retry");
                                    continue; // Переходим к следующей попытке
                                }
                                Log::debug("⚠️ All $maxAttempts attempts failed: No viewer for $link. Skipping.");
                                continue;
                            }

                            // Извлекаем ID изображения
                            $imgId = get_id_from_url($link, self::ID_IMG_PATTERN);
                            if (!$imgId) {
                                Log::error("❌ Cannot extract Image ID from $link");
                                continue;
                            }

                            // Поиск картинки
                            $selector = 'img[data-image-id="' . $imgId . '-curr"]';
                            $imgNodes = $document->find($selector);

                            if (empty($imgNodes)) {
                                // Фолбэк: ищем любой img внутри вьювера
                                $fallbackNodes = $document->find('div[data-testid=media-viewer] img');
                                if (!empty($fallbackNodes)) {
                                    $imgNode = $fallbackNodes[0];
                                } else {
                                    if ($attempts < $maxAttempts) {
                                        Log::debug("⚠️ Attempt #$attempts: Image not found for $link, will retry");
                                        continue;
                                    }
                                    Log::debug("⚠️ All attempts failed: Image not found for $link. Skipping.");
                                    continue;
                                }
                            } else {
                                $imgNode = $imgNodes[0];
                            }

                            $src = $imgNode->getAttribute('src');
                            if (empty($src)) {
                                Log::warning("⚠️ Empty SRC for $link");
                                continue;
                            }

                            // ✅ Успех: сохраняем данные
                            $insertData = [
                                'src' => $src,
                                'srcset' => $imgNode->getAttribute('srcset'),
                                $this->signByField => $id
                            ];

                            $this->insertDB($updateTable, $insertData);
                            Log::info("✅ Saved image for $link (attempt #$attempts)");

                            $processed = true; // Выходим из цикла попыток

                        } catch (\Exception $e) {
                            Log::error("💥 Exception on attempt #$attempts for $link: " . $e->getMessage());
                            if ($attempts < $maxAttempts) {
                                usleep(rand(1000000, 2000000)); // Пауза перед следующей попыткой
                                continue;
                            }
                        }
                    }

                    // Если все попытки исчерпаны
                    if (!$processed) {
                        \Log::warning("❌ Failed to process $link after $maxAttempts attempts");
                    }
                }
            }
        }

        \Log::info("🏁 Finished getImages for table: $updateTable");
    }

    private function logError($document, $selector, $prefix, $url)
    {
        if ($document->has($selector)) {
            \Log::error("$prefix detected in $url");
            return true;
        }
        return false;
    }
}
