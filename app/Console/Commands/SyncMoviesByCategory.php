<?php

namespace App\Console\Commands;

use App\Jobs\TmdbMovieUpdateJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMoviesByCategory extends Command
{
    protected $signature = 'tmdb:sync-category
                            {category_id? : ID категории (если не указан, берется из $quickConfig)}';

    protected $description = 'Диспатчит задачи синхронизации МЕДИА для ВСЕЙ категории целиком (с учетом исключений)';

    private array $typeFilmMap = [
        1 => 'FeatureFilm',
        2 => 'MiniSeries',
        3 => 'ShortFilm',
        4 => 'TvMovie',
        5 => 'TvSeries',
        6 => 'TvShort',
        7 => 'TvSpecial',
        8 => 'Video',
    ];

    public function handle()
    {

        $quickConfig = [
            'category_id' => 90000, // <-- МЕНЯТЬ ТОЛЬКО ЭТО ЗНАЧЕНИЕ ДЛЯ НОВОЙ КАТЕГОРИИ
        ];

        // 🔥 СПИСОК ИСКЛЮЧЕНИЙ (IMDB ID, которые НЕ нужно добавлять в очередь)
        $excludedImdbIds = [
            'tt22408160',
            'tt1998372',
            'tt11324406',
            'tt0374900',
            'tt0439815',
            'tt0887261',
            'tt37654096',
            'tt31591561',
            'tt30796568',
            'tt18328824',
            'tt23131648',
            'tt27000642',
            'tt3631242',
            'tt32646625',
            'tt35669717',
            'tt34867554'
        ];

        $categoryId = (int) ($this->argument('category_id') ?: $quickConfig['category_id']);

        $this->info("🚀 Старт синхронизации МЕДИА для ВСЕЙ категории");
        $this->info("📂 Категория (id_category): <fg=cyan>{$categoryId}</>");
        $this->info("🚫 Исключено из обработки ID: <fg=red>" . count($excludedImdbIds) . "</>");
        $this->newLine();

        // Забираем ВСЕ записи категории, КРОМЕ тех, что в списке исключений
        $movies = DB::table('movie_categories as mc')
            ->join('movies_info as m', 'mc.id_movie', '=', 'm.id_movie')
            ->where('mc.id_category', $categoryId)
            ->whereNotIn('mc.id_movie', $excludedImdbIds)
            ->select('mc.id_movie', 'm.type_film')
            ->orderBy('mc.id_movie', 'asc')
            ->get();

        if ($movies->isEmpty()) {
            $this->warn("⚠️ Фильмы не найдены для категории {$categoryId} (или все они в списке исключений).");
            $this->info("✅ Можно переходить к следующей категории.");
            return Command::SUCCESS;
        }

        $this->info("📊 Найдено фильмов для отправки в очередь: <fg=yellow>{$movies->count()}</>");
        $this->newLine();

        $bar = $this->output->createProgressBar($movies->count());
        $bar->start();

        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($movies as $movie) {
            $typeString = $this->typeFilmMap[$movie->type_film] ?? null;

            if (!$typeString) {
                $this->warn("\n⚠️ Пропущено: {$movie->id_movie}. Неизвестный type_film: {$movie->type_film}");
                $skippedCount++;
                $bar->advance();
                continue;
            }

            TmdbMovieUpdateJob::dispatch(
                movieData: [
                    'id' => $movie->id_movie,
                    'posterType' => 'poster',
                    'typeSync' => 'full_sync',
                ],
                type: $typeString
            )->onQueue('parsing_movie');

            $dispatchedCount++;
            $bar->advance();
            usleep(50000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Готово! Отправлено в очередь: <fg=green>{$dispatchedCount}</>, Пропущено (ошибки типа): <fg=red>{$skippedCount}</>");
        $this->newLine();
        $this->info("💡 СЛЕДУЮЩИЙ ШАГ:");
        $this->line("Откройте файл команды, найдите \$quickConfig и измените 'category_id' на новый.");
        $this->line("Затем запустите команду снова.");

        return Command::SUCCESS;
    }
}
