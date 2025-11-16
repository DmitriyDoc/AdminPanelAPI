<?php

namespace App\Traits\Components;


use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use DiDom\Document;
use Illuminate\Support\Facades\Log;

trait IdByTypeTrait
{
    private function getIdByType($params = [])
    {
        if (empty($this->urls)) {
            return;
        }

        $arrayID = [];

        foreach ($this->urls as $url) {
            // Вручную загружаем HTML
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                    'timeout' => 15,
                ]
            ]);

            $html = @file_get_contents($url, false, $context);

            if ($html === false || trim($html) === '') {
                Log::error("Не удалось загрузить HTML по URL: $url");
                continue;
            }

            try {
                $document = new Document($html, false); // false = не URL, а HTML-строка
            } catch (\Exception $e) {
                Log::error("Ошибка при создании DiDom документа: $url", ['error' => $e->getMessage()]);
                continue;
            }

            if ($document->has('ul.ipc-metadata-list--dividers-between')) {
                $list = $document->find('div.ipc-metadata-list-summary-item__c');
                foreach ($list as $item) {
                    if ($item->has('div.ipc-media__img')) {
                        $titleElements = $item->find('a.ipc-title-link-wrapper');
                        if (empty($titleElements)) {
                            continue;
                        }
                        $title = $titleElements[0];
                        $href = $title->attr('href');

                        if ($this->flagType) {
                            // Фильмы
                            if ($idMovie = get_id_from_url($href, self::ID_PATTERN)) {
                                $arrayID[] = $idMovie;
                            }
                        } else {
                            // Актёры
                            if ($idActor = get_id_from_url($href, self::ACTOR_PATTERN)) {
                                if (!$this->flagNewUpdate) {
                                    if (!$this->checkExists('celebs_info', $idActor)) {
                                        $arrayID[] = $idActor;
                                    }
                                } else {
                                    $arrayID[] = $idActor;
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->urls = [];

        if (!empty($arrayID)) {
            $arrayID = array_unique($arrayID);

            if (!$this->flagType) {
                $this->idCeleb = array_merge($this->idCeleb, $arrayID);
                $parserUpdateCeleb = new ParserUpdateCelebController();
                $parserUpdateCeleb->parseCelebs($this->typeImages, $this->idCeleb);
            } else {
                $parserUpdateMovie = new ParserUpdateMovieController();
                $parserUpdateMovie->parseMovies($params, $arrayID);
            }
        }
    }
}
