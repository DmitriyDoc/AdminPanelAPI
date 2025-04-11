<?php


namespace App\Http\Controllers\Parser;

use App\Events\CurrentPercentageEvent;
use App\Events\ParserReportEvent;
use App\Http\Controllers\ParserController;
use App\Traits\Components\CelebsCreditsTrait;
use App\Traits\Components\IdImagesTrait;
use App\Traits\Components\ImagesTrait;
use App\Traits\Components\CelebsInfoTrait;
use App\Traits\ParserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParserUpdateCelebController extends ParserController
{
    /**
     * Handle the incoming request.
     */
    public function parseCelebs($typeImage,$arrIdCeleb) : void
    {
        $this->signByField = 'id_celeb';
        $this->imgUrlFragment = '/name/';
        $this->chunkSize = 10;

        $this->update_info_table = 'celebs_info';
        $this->update_images_table = 'celebs_images';

        if (!empty($arrIdCeleb)){
            $this->idCeleb = $arrIdCeleb;
        }
        if (!empty($this->idCeleb)){
            $this->parserStart($typeImage);
        }
    }

    public function update(Request $request){
        if ($data = $request->all()){
            $this->signByField = 'id_celeb';
            $this->imgUrlFragment = '/name/';
            $this->chunkSize = 1;

            $this->update_info_table = 'celebs_info';
            $this->update_id_images_table = 'celebs_id_images';
            $this->update_images_table = 'celebs_images';

            if (empty($this->idCeleb)) {
                array_push($this->idCeleb,  $data['data']['id']);
            }

            if (!empty($this->idCeleb)){
                $this->parserStart($data['data']['imageType']);
            }
        }
    }
    public function localizing($celebId) : void
    {
        $updateModel = DB::table('localizing_celebs_info_en')->where($this->signByField,$celebId)->get(['nameActor','id_celeb','filmography','birthdayLocation','dieLocation']);
        if ($updateModel->isNotEmpty()){
            $this->localizing->translateCeleb($updateModel[0],$celebId,$this->signByField);
            $groupKey = ParserController::$personGroup;
            ParserController::$reportProgress['report']['finishInfo'][$groupKey][] = $celebId;
            event(new ParserReportEvent(ParserController::$reportProgress));
            Log::info(">>> LOCALIZING CELEB ID FINISH:",[$celebId]);
        }
    }
    public function parserStart($imageType) : void
    {
        event(new CurrentPercentageEvent(['percent'=>10,'action'=>__('parser.general_data_parser'),'color'=>'']));
        foreach ($this->idCeleb as $id) {
            array_push($this->linksInfo,$this->domen.$this->imgUrlFragment.$id);
            array_push($this->linksCredits,$this->domen.$this->imgUrlFragment.$id.'/');
            array_push($this->linksIdsImages,$this->domen.$this->imgUrlFragment.$id.'/mediaindex?contentTypes='. $imageType);
        }
        $this->linksGetter($this->linksInfo,'getCelebsInfo');

        event(new CurrentPercentageEvent(['percent'=>30,'action'=>__('parser.credits_data_parser'),'color'=>'']));
        $this->linksGetter($this->linksCredits,'credits');

        event(new CurrentPercentageEvent(['percent'=>50,'action'=>__('parser.images_id_parser'),'color'=>'']));
        $this->linksGetter($this->linksIdsImages,'getIdImages',self::ACTOR_PATTERN );

        event(new CurrentPercentageEvent(['percent'=>70,'action'=>__('parser.images_by_id_parser'),'color'=>'']));
        $this->createIdArrayAndGetImages($this->update_images_table,$this->linksImages,$this->idCeleb );

        event(new CurrentPercentageEvent(['percent'=>90,'action'=>__('parser.localization_parser'),'color'=>'']));
        foreach ($this->idCeleb as $id) {
            $this->localizing($id);
        }
        $this->idCeleb = [];
        event(new CurrentPercentageEvent(['percent'=>100,'action'=>__('parser.sync_completed_parser'),'color'=>'success']));
    }
}
