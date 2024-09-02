<?php

namespace App\Http\Controllers\Parser;

use App\Http\Controllers\ParserController;
use App\Traits\Components\IdByTypeTrait;
use App\Traits\ParserTrait;
use Illuminate\Support\Facades\Log;

class ParserIdTypeController extends ParserController
{

    private $url;

    use IdByTypeTrait;
    /**
     * Handle the incoming request.
     */
    public function moviesIdParse(string $spin = 'asc') :void
    {
//        $this->insert_id_table = 'movies_id_type_'.$this->segmentTable;
//        $this->url = "{$this->domen}/search/title/?title_type={$this->titleType}&release_date={$this->dateFrom},{$this->dateTo}&sort={$this->sort},{$spin}";
//        $this->getIdByType();
    }
    public function celebsIdParse(string $spin = 'asc') :void
    {
//        $this->insert_id_table = 'celebs_id';
//        $this->url = "{$this->domen}/search/name/?groups={$this->titleType}&sort={$this->sort},{$spin}";
//        $this->getIdByType();
    }
}
