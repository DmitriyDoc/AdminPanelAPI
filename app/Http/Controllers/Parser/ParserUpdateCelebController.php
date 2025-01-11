<?php


namespace App\Http\Controllers\Parser;

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

        //$this->insert_id_table = 'celebs_id';
        $this->update_info_table = 'celebs_info';
        $this->update_id_images_table = 'celebs_id_images';
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
            session()->push('tracking.report.finishLocalizing', $celebId);
            session()->save();
            Log::info(">>> LOCALIZING CELEB ID FINISH:",[$celebId]);
        }
    }
    public function parserStart($imageType) : void
    {
        foreach ($this->idCeleb as $id) {
            array_push($this->linksInfo,$this->domen.$this->imgUrlFragment.$id);
            array_push($this->linksCredits,$this->domen.$this->imgUrlFragment.$id.'/');
            array_push($this->linksIdsImages,$this->domen.$this->imgUrlFragment.$id.'/mediaindex?contentTypes='. $imageType);
        }
        session()->put('tracking.syncPersonPercentageBar', 10);
        session()->save();
        $this->linksGetter($this->linksInfo,'getCelebsInfo');

        session()->put('tracking.syncPersonPercentageBar', 30);
        session()->save();
        $this->linksGetter($this->linksCredits,'credits');

        session()->put('tracking.syncPersonPercentageBar', 50);
        session()->save();
        $this->linksGetter($this->linksIdsImages,'getIdImages',$this->update_id_images_table,self::ACTOR_PATTERN, $this->signByField);

        session()->put('tracking.syncPersonPercentageBar', 70);
        session()->save();
        $this->createIdArrayAndGetImages($this->update_id_images_table,$this->update_images_table,$this->linksImages,$this->idCeleb);

        foreach ($this->idCeleb as $id) {
            $this->localizing($id);
        }
        $this->idCeleb = [];

        session()->put('tracking.syncPersonPercentageBar', 100);
        session()->save();
    }
}
