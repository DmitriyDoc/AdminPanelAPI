<?php

namespace App\Jobs;

use App\Services\TMDb\TmdbCelebUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class TmdbCelebUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 минут на тяжелые запросы с пулингом
    public $tries = 3;
    public $backoff = [15, 30, 60];
    public $deleteWhenMissingModels = true;

    protected array $celebData;

    public function __construct(array $celebData)
    {
        $this->celebData = $celebData;
    }

    public function handle(TmdbCelebUpdateService $service): void
    {
        $celebId = $this->celebData['id'];
        $lockKey = "tmdb_parsing_celeb_{$celebId}";
        $lock = Cache::lock($lockKey, 900);

        if (!$lock->get()) {
            Log::info("Celeb Job: Person {$celebId} is already being parsed, skipping.");
            return;
        }

        try {
            $randomDelay = random_int(1, 3);
            sleep($randomDelay);

            Log::info("▶️ Celeb Job: Starting API parsing for person {$celebId}");
            $startTime = microtime(true);

            $service->processUpdate($this->celebData);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("✅ Celeb Job: Finished parsing for person {$celebId} in {$duration}s");

        } catch (ConnectionException $e) {
            Log::warning("⚠️ Celeb Job: Network Connection Error for person {$celebId}. Releasing back to queue.");
            throw $e;
        } catch (\Throwable $e) {
            Log::error("Celeb Job failed for person {$celebId}: " . $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::critical("🚨 Celeb Job PERMANENTLY failed for person {$this->celebData['id']}: " . $e->getMessage());
    }

    public function uniqueId(): string
    {
        return "tmdb_celeb_parse_{$this->celebData['id']}";
    }
}
