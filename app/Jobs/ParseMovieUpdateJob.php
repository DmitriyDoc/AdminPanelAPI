<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ParseMovieUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData;
    public $tries = 1;
    public $timeout = 300; // 5 минут
    public function __construct(array $requestData)
    {
        $this->requestData = $requestData;
    }

    public function handle()
    {
        Redis::flushDB();
        $request = new Request($this->requestData);

        (new ParserUpdateMovieController())->update($request);
    }
}
