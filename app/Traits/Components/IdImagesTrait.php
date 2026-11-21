<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

trait IdImagesTrait
{
    protected function getIdImages($pages, $pattern)
    {
        if (!empty($pages)) {
            foreach ($pages as $url => $page) {
                $isPoster = str_contains($url, 'contentTypes=poster');
                $isStillFrame = str_contains($url, 'contentTypes=still_frame');

                // 8000 байт отсекают WAF/капчу, но пропускают страницы с малым кол-вом картинок.
                $maxAttempts = $isPoster ? 4 : ($isStillFrame ? 3 : 2);
                $baseDelay = $isPoster ? 4 : 3; // Чуть увеличили задержку между попытками
                $minContentLength = 8000; // Единый безопасный минимум для всех mediaindex

                $currentMovieId = get_id_from_url($url, $pattern);
                $attempt = 0;
                $success = false;

                while ($attempt < $maxAttempts && !$success) {
                    $attempt++;

                    if ($attempt > 1) {
                        $delay = min(pow($baseDelay, $attempt - 1), 30);
                        Log::info("⏳ Retry #{$attempt} for mediaindex: $url (wait {$delay}s)");
                        sleep($delay);
                    }

                    try {
                        $currentPage = ($attempt === 1) ? $page : null;

                        if (!$currentPage) {
                            $connector = new CurlConnectorController();
                            $result = $connector->fetchMediaIds([$url]);
                            $currentPage = $result[$url] ?? null;
                        }

                        if (empty($currentPage) || !is_string($currentPage)) {
                            Log::warning("⚠️ Attempt #{$attempt}: Empty response for $url");
                            continue;
                        }

                        $contentLen = strlen(trim($currentPage));

                        // 🔧 ИСПРАВЛЕНО: Проверка на WAF/капчу важнее, чем точный размер
                        $isWaf = str_contains($currentPage, 'awsWaf') || str_contains($currentPage, 'Access Denied');
                        $isCaptcha = str_contains($currentPage, 'mp_verify');

                        if ($contentLen < $minContentLength || $isWaf || $isCaptcha) {
                            Log::debug("⚠️ Attempt #{$attempt}: Invalid content (Len: {$contentLen}, WAF: " . ($isWaf ? 'Yes' : 'No') . ") for $url");
                            continue;
                        }

                        $document = new Document(trim($currentPage));

                        if ($this->logErrors($document, "div[class=error_code_404]", "404", $url)) continue;
                        if ($this->logErrors($document, "div[class=errorPage__container]", "500", $url)) continue;

                        // 🔧 ГЛАВНЫЙ КРИТЕРИЙ УСПЕХА: Наличие секции, а не размер файла!
                        if (!$document->has('section[data-testid=sub-section-images]')) {
                            if ($isStillFrame && $attempt >= 2) {
                                Log::info("ℹ️ No still frames section found for $url (this is OK, ending attempts)");
                                $success = true; // Прерываем, т.к. кадров может реально не быть
                                continue;
                            }

                            if ($attempt < $maxAttempts) {
                                Log::debug("⚠️ Attempt #{$attempt}: No images section found for $url, will retry");
                                continue;
                            }

                            Log::warning("❌ No images section for $url after {$maxAttempts} attempts");
                            continue;
                        }

                        // ✅ Успех: сохраняем ID в Redis
                        $this->pushToRedis($document, $currentMovieId);

                        $count = \Illuminate\Support\Facades\Redis::llen($currentMovieId);
                        Log::info("✅ Collected $count image IDs for $currentMovieId from $url (attempt #{$attempt})");

                        $success = true;

                    } catch (\Exception $e) {
                        Log::error("💥 Exception on attempt #{$attempt} for $url: " . $e->getMessage());
                    }
                }

                usleep(rand(200000, 500000)); // 0.2 - 0.5 сек пауза между разными URL
            }
        }
    }

    private function pushToRedis($document, $id)
    {
        if ($container = $document->find('section[data-testid=sub-section-images] div a::attr(href)')) {
            foreach ($container as $element) {
                if ($imgId = get_id_from_url($element, self::ID_IMG_PATTERN)) {
                    Redis::rPush($id, $imgId);
                }
            }
        }
    }
}
