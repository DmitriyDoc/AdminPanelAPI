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


    // app/Traits/Components/ImagesTrait.php

    protected function getImages($linksArray, $updateTable)
    {
        Log::info("🚀 Starting getImages for table: $updateTable", ['movies' => count($linksArray)]);

        foreach ($linksArray as $id => $links) {
            Log::debug("🎬 Processing Movie ID: $id, Links: " . count($links));

            $this->deleteById($updateTable, $this->signByField, $id);

            // 🔧 Обрабатываем пачками по 10 ссылок
            foreach (array_chunk($links, 10) as $chunk) {
                usleep(50000); // 0.05 сек между чанками для безопасности

                $connector = new \App\Http\Controllers\Parser\CurlConnectorController();

                $isPosterTable = str_contains($updateTable, 'poster');

                if ($isPosterTable) {
                    $pages = $connector->fetchViewerPoster($chunk);
                } else {
                    $pages = $connector->fetchViewerImage($chunk);
                }

                if (empty($pages)) {
                    Log::warning("⚠️ Empty pages response for chunk");
                    continue;
                }

                foreach ($pages as $link => $page) {
                    // Если контроллер вернул null или слишком короткий ответ, просто логируем и идем дальше
                    // (Повторные попытки теперь делает сам Node.js сервер, дублировать их тут не нужно)
                    if (!is_string($page) || strlen(trim($page)) < 5000) {
                        $len = is_string($page) ? strlen($page) : 0;
                        Log::debug("⚠️ Invalid or short response for $link (Len: {$len})");
                        continue;
                    }

                    try {
                        $document = new \DiDom\Document(trim($page));

                        if ($this->logError($document, "div[class=error_code_404]", "404", $link)) continue;
                        if ($this->logError($document, "div[class=errorPage__container]", "500", $link)) continue;

                        if (!$document->has('div[data-testid=media-viewer]')) {
                            Log::debug("⚠️ Media viewer container not found for $link");
                            continue;
                        }

                        $imgId = get_id_from_url($link, self::ID_IMG_PATTERN);
                        if (!$imgId) {
                            Log::debug("⚠️ Could not extract imgId from $link");
                            continue;
                        }

                        // Ищем картинку по точному ID, или фолбэк на любую картинку внутри viewer
                        $imgNode = $document->first('img[data-image-id="' . $imgId . '-curr"]');
                        if (!$imgNode) {
                            $imgNode = $document->first('div[data-testid=media-viewer] img');
                        }

                        if (!$imgNode) {
                            Log::debug("⚠️ Image node not found for $link");
                            continue;
                        }

                        $src = $imgNode->getAttribute('src') ?: $imgNode->getAttribute('data-src');
                        if (empty($src)) {
                            Log::debug("⚠️ Image src is empty for $link");
                            continue;
                        }

                        $this->insertDB($updateTable, [
                            'src' => $src,
                            'srcset' => $imgNode->getAttribute('srcset'),
                            $this->signByField => $id
                        ]);
                        Log::info("✅ Saved: $link");

                    } catch (\Exception $e) {
                        Log::error("💥 Error parsing $link: " . $e->getMessage());
                    }
                }
            }
        }

        Log::info("🏁 Finished getImages for table: $updateTable");
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
