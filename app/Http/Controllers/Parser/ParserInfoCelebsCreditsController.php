<?php


namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\CelebsCreditsTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParserInfoCelebsCreditsController extends ParserController
{
    private $links = [];

    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        return;
        $this->signByField = 'id_celeb';
        $this->dateFrom = '2023-06-23';
        $this->select_id_table = 'celebs_id';
        $this->update_info_table = 'celebs_info';

        DB::table($this->select_id_table)->where('created_at','>=', $this->dateFrom)->orderBy('id')->chunk(30, function ($ids) {
            foreach ($ids as $id) {
                array_push($this->links,$this->domen.'/name/'.$id->actor_id.'/fullcredits');
            }
            $pages = (new CurlConnectorController())->getCurlMulty($this->links);
            $this->links = [];
            if (is_array($pages)){
                $this->credits($pages);
            }
        });
    }
}
