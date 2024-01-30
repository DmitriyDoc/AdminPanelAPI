<?php

namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\IdByTypeTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\Log;

class ParserIdTypeController extends ParserController
{
    //celebs ID 7883313 offset 2234250 (2023-06-23)

    private $url;

    use IdByTypeTrait;
    /**
     * Handle the incoming request.
     */
    public function index(array $params,string $spin) :void
    {
        $this->flagType = $params['flagType'];//movies=true;celebs=false;
        $this->dateFrom = $params['dateFrom'];
        $this->dateTo = $params['dateTo'];
        $segment = $params['segmentTable'];
        $sort = $params['sort'];
        $this->titleType = $params['titleType'];
        if ($this->flagType){
            $this->insert_id_table = 'movies_id_type_'.$segment;
            $this->url = "{$this->domen}/search/title/?title_type={$this->titleType}&release_date={$this->dateFrom},{$this->dateTo}&sort={$sort},{$spin}";
        } else {
            $this->insert_id_table = 'celebs_id';
            $this->url = "{$this->domen}/search/name/?groups={$this->titleType}&sort={$params['sort']},{$spin}";
        }
        $this->getIdByType();
    }
}
