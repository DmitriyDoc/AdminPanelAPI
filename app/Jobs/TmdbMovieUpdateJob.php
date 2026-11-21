<?php

namespace App\Jobs;

use App\Services\TMDb\TmdbMovieUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class TmdbMovieUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;
    public $backoff = [15, 30, 60];
    public $deleteWhenMissingModels = true;

    protected array $movieData;
    protected string $type;

    public function __construct(array $movieData, string $type = 'movie')
    {
        $this->movieData = $movieData;
        $this->type = $type;
    }

    public function handle(TmdbMovieUpdateService $service): void
    {
        $movieId = $this->movieData['id'];
        $lockKey = "tmdb_parsing_movie_{$movieId}";
        $lock = Cache::lock($lockKey, 900);

        if (!$lock->get()) {
            Log::info("TMDB Job: Movie {$movieId} is already being parsed, skipping.");
            return;
        }

        try {
            $randomDelay = random_int(1, 3);
            sleep($randomDelay);

            Log::info("▶️ TMDB Job: Starting API parsing for movie {$movieId} (Type: {$this->type}, Sync: {$this->movieData['typeSync']})");
            $startTime = microtime(true);

            $service->processUpdate($this->movieData, $this->type);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("✅ TMDB Job: Finished parsing for movie {$movieId} in {$duration}s");

        } catch (ConnectionException $e) {
            Log::warning("⚠️ TMDB Job: Network Connection Error for movie {$movieId}. Releasing back to queue.");
            throw $e;
        } catch (\Throwable $e) {
            Log::error("TMDB Job failed for movie {$movieId}: " . $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::critical("🚨 TMDB Job PERMANENTLY failed for movie {$this->movieData['id']}: " . $e->getMessage());
    }

    public function uniqueId(): string
    {
        return "tmdb_movie_parse_{$this->type}_{$this->movieData['id']}";
    }
}
