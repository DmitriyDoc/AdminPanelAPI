<?php

namespace App\Console;

use App\Http\Controllers\Parser\ParserIdTypeController;
use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use App\Models\IdTypeMiniSeries;
use App\Models\IdTypeMovies;
use App\Models\IdTypeShortFilm;
use App\Models\IdTypeTvMovie;
use App\Models\IdTypeTvSeries;
use App\Models\IdTypeTvShort;
use App\Models\IdTypeTvSpecial;
use App\Models\IdTypeVideo;
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
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
        ];
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

        //PARSE CELEBS//

//        $schedule->call(function () use ($movieArgs,$celebsGroups) {
//            $movieArgs['flagType'] = false;
//            foreach ($celebsGroups as $group){
//                $movieArgs['titleType'] = $group;
//                $movieArgs['sort'] = 'starmeter'; //birth_date, death_date, starmeter, alpha
//                (new ParserIdTypeController())->startIdParse($movieArgs,'asc');
//                (new ParserIdTypeController())->startIdParse($movieArgs,'desc');
//                Log::info('>>> ARTISAN PARSED CELEBS ID BY:',[ $movieArgs['titleType'] ]);
//            }
//            (new ParserUpdateCelebController())->parseCelebs(date('Y-m-d'));
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
//                (new ParserIdTypeController())->startIdParse($movieArgs,'asc');
//                (new ParserIdTypeController())->startIdParse($movieArgs,'desc');
//                Log::info('>>> START PARSE PERIOD DATE:', [ $value->format('Y-m-d') ]);
//            }
//           (new ParserUpdateMovieController())->index('tv_special',date('Y-m-d'));
//        })->name('parse_diapason_date');//->withoutOverlapping()->everyMinute()

        // ACTUALIZE YEAR and TITLE for tables IdType//
        $schedule->call(function () use ($allowedTableNames) {
            foreach ($allowedTableNames as $table){
                $modelInfo = convertVariableToModelName('Info', $table, ['App', 'Models']);
                $modelIdType = convertVariableToModelName('IdType', $table, ['App', 'Models']);
                $modelInfo = $modelInfo::select('id_movie','title','year_release')->limit(50)->orderBy('created_at','desc')->get();
                foreach ($modelInfo as $key => $item){
                    if (!empty($item['year_release'])){
                        $modelIdType::where('id_movie',$item['id_movie'])->update([
                            'title' => $item['title'],
                            'year' => $item['year_release']
                        ]);
                    }
                }
                Log::info(">>> ACTUALIZE ID TYPE FINISH",[$table]);
            }
        })->name('actualize_years_in_tables_type_id');//->daily()

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
        transaction(function () use ($movieArgs,$segment){
            $parserTypeId = new ParserIdTypeController;
            $parserUpdateMovie = new ParserUpdateMovieController;
            $parserTypeId->moviesIdParse('asc');
            $parserTypeId->moviesIdParse('desc');
            $parserUpdateMovie->index($segment,date('Y-m-d'));
        });
    }

    /**
     * Start local cron path:
     * %progdir%\modules\php\%phpdriver%\php-win.exe -c %progdir%\userdata\temp\config\php.ini -q -f %sitedir%\admin.local/artisan schedule:run
     */
}
