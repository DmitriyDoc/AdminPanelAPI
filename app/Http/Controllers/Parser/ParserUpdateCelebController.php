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
    public function index($tableDateFrom) : void
    {
        $this->signByField = 'id_celeb';
        $this->imgUrlFragment = '/name/';
        $this->chunkSize = 10;
        $this->dateFrom = $tableDateFrom;

        $this->insert_id_table = 'celebs_id';
        $this->update_info_table = 'celebs_info';
        $this->update_id_images_table = 'celebs_id_images';
        $this->update_images_table = 'celebs_images';

        if (empty($this->idCeleb)){
            DB::table($this->insert_id_table)->where('created_at','>=', $this->dateFrom)->orderBy('id')->chunk(50, function ($ids) {
                foreach ($ids as $id) {
                    array_push($this->idCeleb,$id->actor_id);
                }

                if (!empty($this->idCeleb)){
                    foreach ($this->idCeleb as $id) {
                        array_push($this->linksInfo,$this->domen.$this->imgUrlFragment.$id);
                        array_push($this->linksCredits,$this->domen.$this->imgUrlFragment.$id.'/fullcredits');
                        array_push($this->linksIdsImages,$this->domen.$this->imgUrlFragment.$id.'/mediaindex?refine=event'); //publicity event
                    }
                }
                $this->linksGetter($this->linksInfo,'getCelebsInfo');
                $this->linksGetter($this->linksCredits,'credits');
                $this->linksGetter($this->linksIdsImages,'getIdImages',$this->update_id_images_table,self::ACTOR_PATTERN);

                $this->createIdArrayAndGetImages($this->update_id_images_table,$this->update_images_table,$this->linksImages,$this->idCeleb);
                $this->idCeleb = [];
            });
            foreach ($this->idCeleb as $id) {
                $this->localizing($id);
            }
        }
    }

    public function update(Request $request){
        if ($data = $request->all()){
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
                    array_push($this->linksCredits,$this->domen.$this->imgUrlFragment.$id.'/fullcredits');
                    array_push($this->linksIdsImages,$this->domen.$this->imgUrlFragment.$id.'/mediaindex?refine=event'); //publicity event
                }
            }
            $this->linksGetter($this->linksInfo,'getCelebsInfo');
            $this->linksGetter($this->linksCredits,'credits');
            $this->linksGetter($this->linksIdsImages,'getIdImages',$this->update_id_images_table,self::ACTOR_PATTERN, $this->signByField);

            $this->localizing($data['data']['id']);
            $this->createIdArrayAndGetImages($this->update_id_images_table,$this->update_images_table,$this->linksImages,$this->idCeleb);
            $this->touchDB($model, $data['data']['id'],'actor_id');

            $this->idCeleb = [];
        }
    }
    public function localizing($celebId){
        $updateModel = DB::table($this->update_info_table)->where($this->signByField,$celebId)->get(['nameActor','id_celeb','filmography','birthdayLocation','dieLocation']);
        if ($updateModel->isNotEmpty()){
            $this->localizing->translateCeleb($updateModel[0],$celebId,$this->signByField);
        }
    }
}
