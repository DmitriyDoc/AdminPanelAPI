<?php

namespace App\Console;

use App\Http\Controllers\Parser\ParserIdTypeController;
use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $movieArgs = [
            'flagType' => true,
            'dateFrom' =>  date('Y-m-d',strtotime("-1 days")),
            'dateTo' =>  date('Y-m-d',strtotime("-1 days")),
            'segmentTable' =>  '',
            'sort' =>  'moviemeter',
            'titleType' =>  '',
        ];
        $celebsGroups = [
            0 => 'oscar_best_actress_nominees',
            1 => 'oscar_best_actor_nominees',
            2 => 'oscar_best_actress_winners',
            3 => 'oscar_best_actor_winners',
            4 => 'oscar_best_supporting_actress_nominees',
            5 => 'oscar_best_supporting_actor_nominees',
            6 => 'oscar_best_supporting_actor_winners',
            7 => 'oscar_best_director_nominees',
            8 => 'best_director_winner',
            9 => 'oscar_nominee',
            10 => 'emmy_nominee',
            11 => 'golden_globe_nominated',
            12 => 'oscar_winner',
            13 => 'emmy_winner',
            14 => 'golden_globe_winning',
        ];

        // FEATURE FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'feature_film','feature');
            Log::info('>>> ARTISAN PARSED FEATURE FILM FINISH');
        })->name('parse_start_feature_film');//->daily()

        //MINI SERIES FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'mini_series','tv_miniseries');
            Log::info('>>> ARTISAN PARSED MINI SERIES FILM FINISH');
        })->name('parse_start_mini_series')->withoutOverlapping();//->daily()

        //TV SHORT FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'tv_short','tv_short');
            Log::info('>>> ARTISAN PARSED TV SHORT FILM FINISH');
        })->name('parse_start_tv_short')->withoutOverlapping();//->daily()

        //TV MOVIE FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'tv_movie','tv_movie');
            Log::info('>>> ARTISAN PARSED TV MOVIE FILM FINISH');
        })->name('parse_start_tv_movie')->withoutOverlapping();//->daily()

        //SHORT FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'short_film','short');
            Log::info('>>> ARTISAN PARSED SHORT FILM FINISH');
        })->name('parse_start_short_film')->withoutOverlapping();//->daily()

        //TV SERIES FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'tv_series','tv_series');
            Log::info('>>> ARTISAN PARSED TV SERIES FILM FINISH');
        })->name('parse_start_tv_series')->withoutOverlapping();//->daily()

        //VIDEO FILM//

        $schedule->call(function () use ($movieArgs) {
            $this->startParserByType($movieArgs,'video','video');
            Log::info('>>> ARTISAN PARSED VIDEO FILM FINISH');
        })->name('parse_start_video')->withoutOverlapping();//->daily()

        //TV SPECIAL FILM//

        $schedule->call(function () use ($movieArgs) {
           $this->startParserByType($movieArgs,'tv_special','tv_special');
            Log::info('>>> ARTISAN PARSED TV SPECIAL FILM FINISH');
        })->name('parse_start_tv_special')->withoutOverlapping();//->daily()

        //PARSED CELEBS//

//        $schedule->call(function () use ($movieArgs,$celebsGroups) {
//            $movieArgs['flagType'] = false;
//            foreach ($celebsGroups as $grope){
//                $movieArgs['titleType'] = $grope;
//                $movieArgs['sort'] = 'death_date'; //birth_date, death_date, starmeter, alpha
//                (new ParserIdTypeController())->index($movieArgs,'asc');
//                (new ParserIdTypeController())->index($movieArgs,'desc');
//                Log::info('>>> ARTISAN PARSED CELEBS ID BY:',[ $movieArgs['titleType'] ]);
//            }
//            (new ParserUpdateCelebController())->index(date('Y-m-d'));
//            Log::info('>>> ARTISAN PARSED CELEBS FINISH');
//        })->name('parse_start_celebs_id')->withoutOverlapping();//->weekly()

        //TEST PARSE BY PERIOD DATE//

//        $schedule->call(function () use ($movieArgs){
//            $movieArgs['segmentTable'] = 'tv_special';
//            $movieArgs['titleType'] = 'tv_special';
//            $period = new \DatePeriod(
//                new \DateTime('2024-01-01'),
//                new \DateInterval('P1D'),
//                new \DateTime('2024-01-09')
//            );
//            foreach ($period as $key => $value) {
//                $movieArgs['dateFrom'] = $value->format('Y-m-d');
//                $movieArgs['dateTo'] = $value->format('Y-m-d');
//                (new ParserIdTypeController())->index($movieArgs,'asc');
//                (new ParserIdTypeController())->index($movieArgs,'desc');
//                Log::info('>>> START PARSE PERIOD DATE:', [ $value->format('Y-m-d') ]);
//            }
//           (new ParserUpdateMovieController())->index('tv_special',date('Y-m-d'));
//        })->name('parse_diapason_date');//->withoutOverlapping()->everyMinute()
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    private function startParserByType(array $movieArgs, string $segment,string $type):void {
        $movieArgs['segmentTable'] = $segment;
        $movieArgs['titleType'] = $type;
        $parserTypeId = new ParserIdTypeController;
        $parserUpdateMovie = new ParserUpdateMovieController;
        $parserTypeId->index($movieArgs,'asc');
        $parserTypeId->index($movieArgs,'desc');
        $parserUpdateMovie->index($segment,date('Y-m-d'));
    }

    /**
     * Start local cron path:
     * %progdir%\modules\php\%phpdriver%\php-win.exe -c %progdir%\userdata\temp\config\php.ini -q -f %sitedir%\admin.local/artisan schedule:run
     */
}
