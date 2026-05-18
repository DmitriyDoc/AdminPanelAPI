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
use App\Http\Controllers\Parser\ParserUpdateCelebController;

class ParseCelebUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 2;

    // Задержки перед повторными попытками (в секундах)
    public $backoff = [10, 30];

    public $deleteWhenMissingModels = true;

    protected array $celebData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $celebData)
    {
        $this->celebData = $celebData;
    }

    /**
     * Execute the job.
     */
    public function handle(ParserUpdateCelebController $parser): void
    {
        $celebId = $this->celebData['id'];
        $lockKey = "parsing_celeb_{$celebId}";
        $lock = Cache::lock($lockKey, 900);

        if (!$lock->get()) {
            Log::info("Celeb {$celebId} is already being parsed, skipping.");
        }

        try {
            $randomDelay = random_int(1, 3);
            if ($randomDelay > 0) {
                Log::debug("⏱️ Delaying job for celeb {$celebId} by {$randomDelay}s");
                sleep($randomDelay);
            }

            // Формируем запрос так, как ожидает контроллер ParserUpdateCelebController
            // Контроллер ожидает структуру: $request->all()['data']['id'], ['imageType'] и т.д.
            $requestData = [
                'data' => [
                    'id' => $celebId,
                    'imageType' => $this->celebData['posterType'] ?? 'event',
                ]
            ];

            $request = new Request([], [], [], [], [], [], json_encode($requestData));
            $request->headers->set('CONTENT_TYPE', 'application/json');

            Log::info("▶️ Starting parsing for celeb {$celebId}");
            $startTime = microtime(true);

            // Вызываем метод update из контроллера
            $parser->update($request);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("✅ Finished parsing for celeb {$celebId} in {$duration}s");

        } catch (\Throwable $e) {
            Log::error("Job failed for celeb {$celebId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e; // Пробрасываем исключение, чтобы сработал retry или failed
        } finally {
            $lock->release();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical("Job PERMANENTLY failed for celeb {$this->celebData['id']}: " . $e->getMessage());
    }

    /**
     * Уникальный ID для предотвращения дубликатов в очереди (если используете middleware WithoutOverlapping или похожие кастомные решения)
     */
    public function uniqueId(): string
    {
        return "celeb_parse_{$this->celebData['id']}";
    }
}
