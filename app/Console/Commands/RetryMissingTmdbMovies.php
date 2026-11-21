<?php

namespace App\Console\Commands;

use App\Jobs\TmdbMovieUpdateJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RetryMissingTmdbMovies extends Command
{
    protected $signature = 'tmdb:retry-missing
                            {--file=missing_tmdb_ids.txt : Имя файла с ID для повторной обработки}';

    protected $description = 'Читает IMDB ID из текстового файла и отправляет их на повторную синхронизацию через очередь';

    public function handle()
    {
        // =====================================================================
        // 🔥 МЕСТО ДЛЯ ИЗМЕНЕНИЯ ИМЕНИ ФАЙЛА
        // Можно менять прямо здесь или передавать через флаг --file
        // =====================================================================
        $fileName = $this->option('file');
        $filePath = base_path($fileName);

        if (!File::exists($filePath)) {
            $this->error("❌ Файл не найден: {$filePath}");
            $this->info(" Убедитесь, что файл лежит в корне проекта (/var/www/)");
            return Command::FAILURE;
        }

        $ids = array_filter(array_map('trim', File::lines($filePath)->toArray()));

        if (empty($ids)) {
            $this->warn("️ Файл пуст или не содержит валидных IMDB ID.");
            return Command::SUCCESS;
        }

        $this->info("🚀 Найдено ID для повторной обработки: " . count($ids));
        $this->info("📂 Источник: <fg=cyan>{$fileName}</>");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($ids));
        $bar->start();

        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($ids as $imdbId) {
            // Валидация формата ttXXXXXXX
            if (!preg_match('/^tt\d{7,10}$/', $imdbId)) {
                $skippedCount++;
                $bar->advance();
                continue;
            }

            // Отправляем в ту же очередь, что и обычная синхронизация
            // full_sync запустит attemptToResolveTmdbId, который сам всё исправит
            TmdbMovieUpdateJob::dispatch(
                movieData: [
                    'id' => $imdbId,
                    'posterType' => 'poster',
                    'typeSync' => 'full_sync',
                ],
                type: 'FeatureFilm' // Передаем дефолтный тип, resolve-метод его перепишет если нужно
            )->onQueue('parsing_movie');

            $dispatchedCount++;
            $bar->advance();
            usleep(50000); // Микро-пауза для защиты Redis
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Готово! Отправлено в очередь: <fg=green>{$dispatchedCount}</>, Пропущено (невалидный формат): <fg=red>{$skippedCount}</>");
        $this->newLine();
        $this->info("💡 Следите за воркером: docker logs -f spectrum_worker");
        $this->line("Логи успешного разрешения будут помечены как: <fg=yellow>🔄 TMDB Resolve</>");

        return Command::SUCCESS;
    }
}
