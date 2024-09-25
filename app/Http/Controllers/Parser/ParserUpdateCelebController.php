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

        $this->insert_id_table = 'celebs_id';
        $this->update_info_table = 'celebs_info';
        $this->update_id_images_table = 'celebs_id_images';
        $this->update_images_table = 'celebs_images';

        if (!empty($arrIdCeleb)){
            foreach ($arrIdCeleb as $id) {
                array_push($this->linksInfo,$this->domen.$this->imgUrlFragment.$id);
                array_push($this->linksCredits,$this->domen.$this->imgUrlFragment.$id.'/');
                array_push($this->linksIdsImages,$this->domen.$this->imgUrlFragment.$id.'/mediaindex?contentTypes='.$typeImage);
            }
        }
        $this->linksGetter($this->linksInfo,'getCelebsInfo');
        $this->linksGetter($this->linksCredits,'credits');
        $this->linksGetter($this->linksIdsImages,'getIdImages',$this->update_id_images_table,self::ACTOR_PATTERN,$this->signByField);

        $this->createIdArrayAndGetImages($this->update_id_images_table,$this->update_images_table,$this->linksImages,$this->idCeleb);

        foreach ($this->idCeleb as $id) {
            $this->localizing($id);
        }
    }

    public function update(Request $request){
        if ($data = $request->all()){
            $request->session()->forget('syncPersonPercentageBar');
            $request->session()->put('syncPersonPercentageBar', 0);
            session()->save();

            $model = convertVariableToModelName('IdType', $data['data']['type'], ['App', 'Models']);

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
                foreach ($this->idCeleb as $id) {
                    array_push($this->linksInfo,$this->domen.$this->imgUrlFragment.$id);
                    array_push($this->linksCredits,$this->domen.$this->imgUrlFragment.$id.'/');
                    array_push($this->linksIdsImages,$this->domen.$this->imgUrlFragment.$id.'/mediaindex?contentTypes='. $data['data']['imageType']);
                }
            }
            $request->session()->put('syncPersonPercentageBar', 10);
            session()->save();
            $this->linksGetter($this->linksInfo,'getCelebsInfo');
            $request->session()->put('syncPersonPercentageBar', 30);
            session()->save();
            $this->linksGetter($this->linksCredits,'credits');
            $request->session()->put('syncPersonPercentageBar', 50);
            session()->save();
            $this->linksGetter($this->linksIdsImages,'getIdImages',$this->update_id_images_table,self::ACTOR_PATTERN, $this->signByField);
            $request->session()->put('syncPersonPercentageBar', 70);
            session()->save();
            $this->localizing($data['data']['id']);
            $this->createIdArrayAndGetImages($this->update_id_images_table,$this->update_images_table,$this->linksImages,$this->idCeleb);
            $this->touchDB($model, $data['data']['id'],'actor_id');

            $this->idCeleb = [];
            $request->session()->put('syncPersonPercentageBar', 100);
            session()->save();
        }
    }
    public function localizing($celebId){
        $updateModel = DB::table($this->update_info_table)->where($this->signByField,$celebId)->get(['nameActor','id_celeb','filmography','birthdayLocation','dieLocation']);
        if ($updateModel->isNotEmpty()){
            $this->localizing->translateCeleb($updateModel[0],$celebId,$this->signByField);
        }
    }
}
