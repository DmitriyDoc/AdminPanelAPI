<?php

namespace App\Http\Controllers;

use App\Events\ParserReportEvent;
use App\Http\Controllers\Parser\CurlConnectorController;
use App\Http\Controllers\Parser\ParserStartController;
use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use App\Traits\Components\CelebsCreditsTrait;
use App\Traits\Components\CelebsInfoTrait;
use App\Traits\Components\ChartParserTrait;
use App\Traits\Components\IdByTypeTrait;
use App\Traits\Components\IdImagesTrait;
use App\Traits\Components\ImagesTrait;
use App\Traits\Components\MoviesInfoTrait;
use App\Traits\ParserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;



class ParserController extends Controller
{
    const ID_PATTERN = '/tt\d{1,10}/';
    const TITLE_PATTERN = "/^[^\s]*\s[^\s]*/";
    const YEAR_PATTERN = "/\d\d\d\d/";
    const BUDGET_PATTERN = "/[^(*]+/";
    const ACTOR_PATTERN = "/nm\d{1,10}/";
    const ID_IMG_PATTERN = '/rm\d{1,10}/';
    const CLEAR_DIGITS_PATTERN = '/^\d*\.\s/';

    protected $update_id_images_table;
    protected $update_id_posters_table;
    protected $update_info_table;
    protected $update_en_info_table;
    protected $update_images_table;
    protected $update_posters_table;

    protected $occupations = [
        0 => 'actor',
        1 => 'actress',
        2 => 'producer',
        3 => 'writer',
        5 => 'soundtrack',
        6 => 'director',
        7 => 'composer',
//        8 => 'production_manager',
//        9 => 'miscellaneous',
//        10 => 'self',
//        11 => 'editor',
//        12 => 'cinematographer',
//        13 => 'costume_designer',
//        14 => 'production_designer',
//        15 => 'art_director',
//        16 => 'music_department',
//        17 => 'visual_effects',
//        18 => 'assistant_director',
//        19 => 'camera_department',
//        20 => 'archive_footage',
//        21 => 'thanks',
//        22 => 'casting_director',
//        23 => 'casting_department',
//        24 => 'make_up_department',
//        25 => 'art_department',
//        26 => 'set_decorator',
//        27 => 'transportation_department',
//        28 => 'special_effects',
//        29 => 'animation_department',
//        30 => 'sound_department',
    ];

    public $allowedTableNames = [
        'FeatureFilm' => [
            'segment' => 'feature_film',
            'type' => 'feature',
        ],
        'MiniSeries' => [
            'segment' => 'mini_series',
            'type' => 'tv_miniseries',
        ],
        'ShortFilm' => [
            'segment' => 'short_film',
            'type' => 'short',
        ],
        'TvMovie' => [
            'segment' => 'tv_movie',
            'type' => 'tv_movie',
        ],
        'TvSeries' => [
            'segment' => 'tv_series',
            'type' => 'tv_series',
        ],
        'TvShort' => [
            'segment' => 'tv_short',
            'type' => 'tv_short',
        ],
        'TvSpecial' => [
            'segment' => 'tv_special',
            'type' => 'tv_special',
        ],
        'Video' => [
            'segment' => 'video',
            'type' => 'video',
        ],
    ];
    protected $domen = 'https://www.imdb.com';
    protected $signByField;
    protected $typeFilm;
    protected $imgUrlFragment;
    protected $chunkSize;
    protected $localizing;

    protected $start = 0;
    protected $urls = [];
    protected $dateFrom;
    protected $dateTo;
    protected $flagType;//movies=true;celebs=false;
    protected $flagNewUpdate;//new=true;  update=false;

    protected $titleType;

    protected $sort;
    protected $typeImages;
    protected $typePosters;
    protected $idMovies = [];
    protected $idCeleb = [];
    protected $linksInfo = [];
    protected $linksIdsImages = [];
    protected $linksIdsPosters = [];
    protected $linksImages = [];
    protected $linksPosters = [];
    protected $linksCredits = [];


// 🔧 НАСТРОЙКИ ЗАДЕРЖЕК ДЛЯ ПАРСИНГА ПО ДАТАМ (чтобы не забанили)
    protected const DELAY_BETWEEN_DATES_MIN = 3;        // Мин. пауза между датами (сек)
    protected const DELAY_BETWEEN_DATES_MAX = 7;        // Макс. пауза между датами
    protected const DELAY_BETWEEN_MOVIE_TYPES = 5;      // Пауза между типами фильмов (сек)
    protected const DELAY_BETWEEN_SEARCH_REQUESTS_MIN = 1; // Пауза между /search/title/ запросами
    protected const DELAY_BETWEEN_SEARCH_REQUESTS_MAX = 2;
    protected const DELAY_BEFORE_MOVIE_PARSE_MIN = 1;   // Пауза перед парсингом каждого фильма
    protected const DELAY_BEFORE_MOVIE_PARSE_MAX = 3;
    protected const DELAY_BETWEEN_CHARTS_MIN = 3;        // Мин. пауза между чартами (сек)
    protected const DELAY_BETWEEN_CHARTS_MAX = 7;        // Макс. пауза между чартами
    protected const DEFAULT_CHART_START_ID = 1;          // Начальный ID чарта по умолчанию
    protected const DEFAULT_CHART_END_ID = 313;          // Конечный ID чарта по умолчанию

    public static $reportProgress = [
        'report'=> [
            'start' => null,
            'stop' => null,
            'finishIdsPeriod' => [],
            'finishLocalizing' => [],
            'finishInfo' => [],
            'statusBar'=> [
                'percent' => 0,
                'action' => null,
                'color' => null,
            ]
        ],
    ];

    public static $personGroup;

    use ParserTrait, IdByTypeTrait, MoviesInfoTrait, IdImagesTrait, ImagesTrait, CelebsInfoTrait, CelebsCreditsTrait, ChartParserTrait;
    public function __construct($params = [])
    {
        if (!empty($params)){
            $this->flagType = $params['flag'];
            $this->dateFrom = $params['date_from'];
            $this->dateTo = $params['date_till'];
            $this->sort = $params['sort'];
            $this->typeImages = $params['type_images'];
            $this->typePosters = $params['type_posters'];
        }
        $this->localizing = new TranslatorController();
    }

    public function __call( $name, $arguments ) {
        call_user_func($name, $arguments);
    }
    protected function createIdArrayAndGetImages( $imagesTable, $linksArray, $Ids) {
        foreach ($Ids as $id){
            if(Redis::exists($id)) {
                $picsIds = Redis::lrange($id,0, -1);
                foreach ($picsIds as $picId){
                    $linksArray[$id][] = $this->domen . $this->imgUrlFragment . $id . '/mediaviewer/' . $picId;
                }
                Redis::del($id);
            }
        }
        //dump(Redis::keys('*'));
        if (!empty($linksArray)){
            $this->getImages( $linksArray, $imagesTable );
        }
    }

    protected function linksGetter($links, $methodName, $mode = 'images', $pattern = null)
    {
        if (!empty($links)) {
            $infoChunks = array_chunk($links, $this->chunkSize);
            $connector = new \App\Http\Controllers\Parser\CurlConnectorController();

            foreach ($infoChunks as $chunkIndex => $chunk) {

                // 🔧 ОБНОВЛЕННАЯ ЛОГИКА: вызываем новые специализированные методы
                if ($mode === 'info') {
                    // Для главных страниц фильмов (/title/), поиска (/search/) и чартов (/chart/)
                    $pages = $connector->fetchMovieInfo($chunk);

                } elseif ($mode === 'filmography') {
                    // Для страниц актеров и их фильмографии (/name/)
                    $pages = $connector->fetchCelebCredits($chunk);

                } elseif ($mode === 'images') {
                    // Для страниц индексов картинок (/mediaindex?contentTypes=...)
                    $pages = $connector->fetchMediaIds($chunk);

                } else {
                    Log::warning("⚠️ Unknown mode '$mode' in linksGetter for method $methodName");
                    continue;
                }

                if (is_array($pages) && !empty($pages)) {
                    $this->{$methodName}($pages, $pattern);
                } else {
                    Log::warning("⚠️ Empty response for chunk in $methodName");
                }
            }
        }
    }

    /**
     * Массовый парсинг всех IMDb Charts (in0000001 - in0000313)
     *
     * @param int $startId Начальный ID (по умолчанию 1)
     * @param int $endId Конечный ID (по умолчанию 313)
     */
    public function parseAllCharts(int $startId = self::DEFAULT_CHART_START_ID, int $endId = self::DEFAULT_CHART_END_ID): void
    {
        Log::info("🚀 Starting mass chart parsing", [
            'start_id' => $startId,
            'end_id' => $endId
        ]);

        $this->flagType = true;

        $totalCharts = $endId - $startId + 1;
        $successCount = 0;
        $failCount = 0;

        // 🔧 Инициализация прогресс-бара
        self::$reportProgress['report']['statusBar']['percent'] = 0;
        self::$reportProgress['report']['statusBar']['action'] = __('parser.charts_start_parsing', [
            'total' => $totalCharts
        ]);
        self::$reportProgress['report']['statusBar']['color'] = 'primary';
        event(new ParserReportEvent(self::$reportProgress));

        event(new \App\Events\ChartProgressEvent([
            'percent' => 0,
            'current' => 0,
            'total' => $totalCharts,
            'current_chart_id' => null,
            'action' => __('parser.charts_start_parsing', ['total' => $totalCharts]),
            'color' => 'primary',
            'status' => 'start',
        ]));

        for ($i = $startId; $i <= $endId; $i++) {
            $chartId = str_pad($i, 7, '0', STR_PAD_LEFT);
            $fullChartId = "in{$chartId}";
            $chartUrl = "{$this->domen}/chart/titles/{$fullChartId}/?view=compact";

            $progress = floor((($i - $startId + 1) / $totalCharts) * 100);
            self::$reportProgress['report']['statusBar']['percent'] = $progress;
            self::$reportProgress['report']['statusBar']['action'] = __('parser.chart_processing', [
                'id' => $fullChartId,
                'current' => $i - $startId + 1,
                'total' => $totalCharts
            ]);
            self::$reportProgress['report']['statusBar']['color'] = 'warning';
            event(new ParserReportEvent(self::$reportProgress));

            // 🔧 ИСПРАВЛЕНО: status остаётся 'processing', а не 'success'
            event(new \App\Events\ChartProgressEvent([
                'percent' => $progress,
                'current' => $i - $startId + 1,
                'total' => $totalCharts,
                'current_chart_id' => $fullChartId,
                'action' => __('parser.chart_processing', [
                    'id' => $fullChartId,
                    'current' => $i - $startId + 1,
                    'total' => $totalCharts
                ]),
                'color' => 'warning',
                'status' => 'processing',
            ]));

            Log::info("📊 Processing chart", [
                'chart_id' => $fullChartId,
                'progress' => "{$i}/{$totalCharts}",
                'percent' => "{$progress}%"
            ]);

            try {
                $this->urls = [$chartUrl];
                $arg = [
                    'segment' => 'chart',
                    'typeImages' => $this->typeImages,
                    'typePosters' => $this->typePosters,
                    'is_chart' => true,
                ];

                $this->getIdByType($arg);
                $successCount++;

                array_push(self::$reportProgress['report']['finishIdsPeriod'], __('parser.chart_parsed_success', [
                    'id' => $fullChartId
                ]));
                event(new ParserReportEvent(self::$reportProgress));

                // 🔧 ИСПРАВЛЕНО: status остаётся 'processing', т.к. общий процесс ещё идёт
                // Только последнее событие (когда $i === $endId) получит 'completed' ниже
                if ($i < $endId) {
                    event(new \App\Events\ChartProgressEvent([
                        'percent' => $progress,
                        'current' => $i - $startId + 1,
                        'total' => $totalCharts,
                        'current_chart_id' => $fullChartId,
                        'action' => __('parser.chart_parsed_success', ['id' => $fullChartId]),
                        'color' => 'success',
                        'status' => 'processing', // 🔧 было 'success'
                    ]));

                    $delay = random_int(self::DELAY_BETWEEN_CHARTS_MIN, self::DELAY_BETWEEN_CHARTS_MAX);
                    Log::info("😴 Sleeping {$delay}s between charts...");
                    sleep($delay);
                }

            } catch (\Exception $e) {
                Log::error("❌ Failed to parse chart", [
                    'chart_id' => $fullChartId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $failCount++;

                array_push(self::$reportProgress['report']['finishIdsPeriod'], __('parser.chart_parsed_failed', [
                    'id' => $fullChartId,
                    'error' => $e->getMessage()
                ]));
                event(new ParserReportEvent(self::$reportProgress));

                // 🔧 ИСПРАВЛЕНО: status остаётся 'processing', т.к. общий процесс ещё идёт
                event(new \App\Events\ChartProgressEvent([
                    'percent' => $progress,
                    'current' => $i - $startId + 1,
                    'total' => $totalCharts,
                    'current_chart_id' => $fullChartId,
                    'action' => __('parser.chart_parsed_failed', [
                        'id' => $fullChartId,
                        'error' => $e->getMessage()
                    ]),
                    'color' => 'danger',
                    'status' => 'processing', // 🔧 было 'error'
                ]));
            }
        }

        // 🔧 Финальный отчет — вот здесь отправляем 'completed'
        self::$reportProgress['report']['statusBar']['percent'] = 100;
        self::$reportProgress['report']['statusBar']['action'] = __('parser.charts_parsing_completed', [
            'success' => $successCount,
            'failed' => $failCount,
            'total' => $totalCharts
        ]);
        self::$reportProgress['report']['statusBar']['color'] = $failCount > 0 ? 'warning' : 'success';
        event(new ParserReportEvent(self::$reportProgress));

        event(new \App\Events\ChartProgressEvent([
            'percent' => 100,
            'current' => $totalCharts,
            'total' => $totalCharts,
            'current_chart_id' => null,
            'action' => __('parser.charts_parsing_completed', [
                'success' => $successCount,
                'failed' => $failCount,
                'total' => $totalCharts
            ]),
            'color' => $failCount > 0 ? 'warning' : 'success',
            'status' => 'completed', // 🔧 Только здесь 'completed'
            'success_count' => $successCount,
            'failed_count' => $failCount,
        ]));

        Log::info("🏁 Mass chart parsing completed", [
            'total' => $totalCharts,
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * Парсинг одного чарта по ID (универсальный метод для новых и существующих)
     * URL формируется автоматически из chart_id
     *
     * @param string $chartID ID чарта (например, 'in0000017' или 'ls1234567')
     */
    public function parseChart(string $chartID): void
    {
        // 🔧 Формируем URL автоматически из chart_id
        $chartUrl = "{$this->domen}/chart/titles/{$chartID}/?view=compact";

        Log::info("🧪 Parsing chart", ['chart_id' => $chartID, 'url' => $chartUrl]);

        // 🔧 КРИТИЧНО: Устанавливаем флаг для парсинга ФИЛЬМОВ (не актёров)
        $this->flagType = true;

        // 🔧 Инициализация прогресс-бара
        self::$reportProgress['report']['statusBar']['percent'] = 10;
        self::$reportProgress['report']['statusBar']['action'] = __('parser.chart_single_start', [
            'id' => $chartID
        ]);
        self::$reportProgress['report']['statusBar']['color'] = 'primary';
        event(new ParserReportEvent(self::$reportProgress));

        // 🔧 НОВОЕ: Отправляем событие для прогресс-бара чартов
        event(new \App\Events\ChartProgressEvent([
            'percent' => 10,
            'current' => 1,
            'total' => 1,
            'current_chart_id' => $chartID,
            'action' => __('parser.chart_single_start', ['id' => $chartID]),
            'color' => 'primary',
            'status' => 'start',
        ]));

        try {
            $this->urls = [$chartUrl];
            $arg = [
                'segment' => 'chart',
                'typeImages' => $this->typeImages,
                'typePosters' => $this->typePosters,
                'is_chart' => true,
            ];

            self::$reportProgress['report']['statusBar']['percent'] = 50;
            self::$reportProgress['report']['statusBar']['action'] = __('parser.chart_single_processing', [
                'id' => $chartID
            ]);
            self::$reportProgress['report']['statusBar']['color'] = 'warning';
            event(new ParserReportEvent(self::$reportProgress));

            // 🔧 НОВОЕ: Отправляем событие для прогресс-бара чартов
            event(new \App\Events\ChartProgressEvent([
                'percent' => 50,
                'current' => 1,
                'total' => 1,
                'current_chart_id' => $chartID,
                'action' => __('parser.chart_single_processing', ['id' => $chartID]),
                'color' => 'warning',
                'status' => 'processing',
            ]));

            $this->getIdByType($arg);

            // 🔧 Успешное завершение
            self::$reportProgress['report']['statusBar']['percent'] = 100;
            self::$reportProgress['report']['statusBar']['action'] = __('parser.chart_single_success', [
                'id' => $chartID
            ]);
            self::$reportProgress['report']['statusBar']['color'] = 'success';
            event(new ParserReportEvent(self::$reportProgress));

            array_push(self::$reportProgress['report']['finishIdsPeriod'], __('parser.chart_parsed_success', [
                'id' => $chartID
            ]));
            event(new ParserReportEvent(self::$reportProgress));

            // 🔧 НОВОЕ: Финальное событие для прогресс-бара чартов
            event(new \App\Events\ChartProgressEvent([
                'percent' => 100,
                'current' => 1,
                'total' => 1,
                'current_chart_id' => $chartID,
                'action' => __('parser.chart_single_success', ['id' => $chartID]),
                'color' => 'success',
                'status' => 'completed',
            ]));

            Log::info("✅ Chart parsed successfully", ['chart_id' => $chartID]);

        } catch (\Exception $e) {
            Log::error("❌ Failed to parse chart", [
                'chart_id' => $chartID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 🔧 Ошибка
            self::$reportProgress['report']['statusBar']['percent'] = 100;
            self::$reportProgress['report']['statusBar']['action'] = __('parser.chart_single_failed', [
                'id' => $chartID,
                'error' => $e->getMessage()
            ]);
            self::$reportProgress['report']['statusBar']['color'] = 'danger';
            event(new ParserReportEvent(self::$reportProgress));

            // 🔧 НОВОЕ: Финальное событие об ошибке
            event(new \App\Events\ChartProgressEvent([
                'percent' => 100,
                'current' => 1,
                'total' => 1,
                'current_chart_id' => $chartID,
                'action' => __('parser.chart_single_failed', [
                    'id' => $chartID,
                    'error' => $e->getMessage()
                ]),
                'color' => 'danger',
                'status' => 'error',
            ]));

            throw $e;
        }
    }
    public function parseMoviesByPeriod($allowMovieTypes) : void
    {
        $period = new \DatePeriod(
            new \DateTime($this->dateFrom),
            new \DateInterval('P1D'),
            new \DateTime($this->dateTo . '23:59')
        );

        // 🔧 Собираем все даты в массив для удобного обхода
        $days = [];
        foreach ($period as $day) {
            $days[] = $day;
        }

        $totalDays = count($days);
        $totalTypes = count($allowMovieTypes);
        $totalIterations = $totalDays * $totalTypes;
        $currentIteration = 0;

        Log::info("🚀 Starting period parsing", [
            'days' => $totalDays,
            'types' => $totalTypes,
            'total_iterations' => $totalIterations,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);

        // 🔧 ВНЕШНИЙ цикл — по ДАТАМ
        foreach ($days as $dayIndex => $day) {
            $dateStr = $day->format('Y-m-d');

            // 🔧 ВНУТРЕННИЙ цикл — по ТИПАМ фильмов
            foreach ($allowMovieTypes as $typeIndex => $type) {
                if (!array_key_exists($type, $this->allowedTableNames)) {
                    continue;
                }

                $currentIteration++;
                $this->titleType = $this->allowedTableNames[$type]['type'];

                $arg = [
                    'segment' => $this->allowedTableNames[$type]['segment'],
                    'typeImages' => $this->typeImages,
                    'typePosters' => $this->typePosters,
                ];

                // 🔧 Формируем ОДИН URL для одной даты и одного типа
                $url = "{$this->domen}/search/title/?title_type={$this->titleType}&release_date={$dateStr},{$dateStr}&sort={$this->sort},asc";

                Log::info("📍 Iteration [{$currentIteration}/{$totalIterations}]", [
                    'date' => $dateStr,
                    'type' => $this->titleType,
                    'url' => $url,
                ]);

                // 🔧 Прогресс-бар
                $progress = floor(($currentIteration / $totalIterations) * 90);
                self::$reportProgress['report']['statusBar']['percent'] = max(5, $progress);
                self::$reportProgress['report']['statusBar']['action'] = __('parser.section_processing_parser', [
                    'type' => strtoupper($this->titleType),
                    'date' => $dateStr
                ]);
                self::$reportProgress['report']['statusBar']['color'] = 'danger';
                event(new ParserReportEvent(self::$reportProgress));

                // 🔧 Отправляем ОДИН URL — ID не будут смешиваться
                $this->urls = [$url];

                // 🔧 Пауза между запросами (имитация "человеческого" поведения)
                if ($currentIteration > 1) {
                    $delay = random_int(self::DELAY_BETWEEN_DATES_MIN, self::DELAY_BETWEEN_DATES_MAX);
                    Log::info("😴 Sleeping {$delay}s before next request...");
                    sleep($delay);
                }

                $this->getIdByType($arg);

                // 🔧 Отчёт о завершении
                array_push(self::$reportProgress['report']['finishIdsPeriod'], __('parser.finish_period_parser', [
                    'type' => strtoupper($this->titleType),
                    'date' => $dateStr
                ]));
                event(new ParserReportEvent(self::$reportProgress));

                Log::info(">>> PARSE PERIOD: {$dateStr} IDS FINISH FOR ->>>", [
                    $this->titleType
                ]);
            }

            // 🔧 Пауза между датами (после обработки всех типов для текущей даты)
            if ($dayIndex < $totalDays - 1) {
                $delay = random_int(self::DELAY_BETWEEN_DATES_MIN, self::DELAY_BETWEEN_DATES_MAX);
                Log::info("😴 Sleeping {$delay}s between dates (after all types for {$dateStr})...");
                sleep($delay);
            }
        }

        Log::info("🏁 Period parsing completed", [
            'total_iterations' => $totalIterations,
            'days' => $totalDays,
            'types' => $totalTypes,
        ]);
    }

    public function parsePersons($personsSource,$switchNewUpdate) : void
    {
        foreach ($personsSource as $k => $group) {
            $this->flagNewUpdate = $switchNewUpdate;
            self::$personGroup = $group['label'];
            self::$reportProgress['report']['statusBar']['percent'] = floor((($k/count($personsSource))*100));
            self::$reportProgress['report']['statusBar']['action'] = __('parser.group_processing_parser',['group' => $group['label']]);
            self::$reportProgress['report']['statusBar']['color'] = 'danger';
            if (!$k){
                self::$reportProgress['report']['statusBar']['percent'] = 5;
            }
            event(new ParserReportEvent(self::$reportProgress));

            array_push($this->urls,"{$this->domen}/search/name/{$group['value']}&sort={$this->sort},asc");
            $this->getIdByType();
            Log::info('>>> PARSE CELEBS ID BY:', [$group['value']]);
        }
        Log::info('>>> PARSED CELEBS FINISH');
    }
}
