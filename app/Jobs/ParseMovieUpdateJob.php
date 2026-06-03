<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Parser\ParserUpdateMovieController;

class ParseMovieUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 2;

    // 🔧 ИЗМЕНЕНИЕ: Короткие задержки для ретраев внутри одного джоба
    public $backoff = [10, 30]; // 10 сек, затем 30 сек (вместо 60/120)

    public $deleteWhenMissingModels = true;

    protected array $movieData;
    protected string $type;

    public function __construct(array $movieData, string $type = 'movie')
    {
        $this->movieData = $movieData;
        $this->type = $type;
    }

    public function handle(ParserUpdateMovieController $parser): void
    {
        $movieId = $this->movieData['id'];
        $lockKey = "parsing_movie_{$movieId}";

        $lock = Cache::lock($lockKey, 900);

        if (!$lock->get()) {
            Log::info("Movie {$movieId} is already being parsed, skipping.");
            return;
        }

        try {
            // 🔧 ИЗМЕНЕНИЕ: Короткая случайная пауза 1-3 секунды (вместо 5-15)
            $randomDelay = random_int(1, 3);
            if ($randomDelay > 0) {
                Log::debug("⏱️ Delaying job for movie {$movieId} by {$randomDelay}s");
                sleep($randomDelay);
            }

            $requestData = [
                'data' => [
                    'type' => $this->type,
                    'id' => $movieId,
                    'posterType' => $this->movieData['posterType'] ?? 'poster',
                    'typeSync' => $this->movieData['typeSync'],
                ]
            ];

            $request = new Request([], [], [], [], [], [], json_encode($requestData));
            $request->headers->set('CONTENT_TYPE', 'application/json');

            Log::info("▶️ Starting parsing for movie {$movieId}");
            $startTime = microtime(true);

            $parser->update($request);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("✅ Finished parsing for movie {$movieId} in {$duration}s");

        } catch (\Throwable $e) {
            Log::error("Job failed for movie {$movieId}: " . $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::critical("Job PERMANENTLY failed for movie {$this->movieData['id']}: " . $e->getMessage());
    }

    public function uniqueId(): string
    {
        return "movie_parse_{$this->type}_{$this->movieData['id']}";
    }
}
