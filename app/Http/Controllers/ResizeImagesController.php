<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ResizeImagesController
{
    private const imageLimit = 20;
    private $file;
    private $movieId;
    private $movieType;

    private $dirPosterName;
    private $posterOriginalId;
    private $posterRussianId;
    private $posterCharactersId = [];
    private $posterAlternativeId = [];
    private $posterWallpaperId;
    private $modelPoster;
    private $modelImages;

    public function index (Request $request)
    {
        ini_set('memory_limit', '1G');
        $this->movieId = get_id_from_url($request->get('id_movie') ?? null,'/tt\d{1,10}/') ?? null;
        if (!empty($this->movieId)){
            $assignPosters = AssignPoster::where('id_movie',$this->movieId)->get()->toArray();
            if (!empty($assignPosters)){
                $this->movieType = $assignPosters[0]['type_film'];
                $this->posterOriginalId = $assignPosters[0]['id_poster_original'] ?? null;
                $this->posterRussianId = $assignPosters[0]['id_poster_ru'] ?? null;
                $this->posterCharactersId = json_decode($assignPosters[0]['id_posters_characters'],true) ?? [];
                $this->posterAlternativeId = json_decode($assignPosters[0]['id_posters_alternative'],true) ?? [];
                $this->posterWallpaperId = $assignPosters[0]['id_wallpaper'] ?? null;
                $this->resizePosters();
                $this->resizeImages();
                $this->deleteTempDirectory();
            }
        }
        return [
            'success' => true,
            'status' => 200,
        ];
    }
    private function resizeImages()
    {
        $this->modelImages = convertVariableToModelName('Images',getTableSegmentOrTypeId($this->movieType), ['App', 'Models']);
        $moviesImages = $this->modelImages::where('id_movie',$this->movieId)->inRandomOrder()->limit(self::imageLimit)->get(['src','id'])->toArray();
        $this->dirPosterName = 'images';
        $this->makeResizeDirectory(['small','full_size']);
        if (!empty($moviesImages)){
            foreach ($moviesImages as $image){
                $this->getFileFromSrc($image['src']);
                $this->putFileToTempDirectory($image['id']);
                $this->saveImages($image['id']);
            }
        }
    }
    private function resizePosters()
    {
        $this->modelPoster = convertVariableToModelName('Posters',getTableSegmentOrTypeId($this->movieType), ['App', 'Models']);
        $this->compressOriginalPoster();
        $this->compressRussianPoster();
        $this->compressCharactersPoster();
        $this->compressAlternativePoster();
        $this->compressWallpaperPoster();
    }
    private function compressWallpaperPoster()
    {
        $wallpaperId = $this->modelPoster::where('id',$this->posterWallpaperId)->where('id_movie',$this->movieId)->get('src')->toArray();
        $this->dirPosterName = 'wallpaper';
        if (!empty($wallpaperId)){
            $this->getFileFromSrc($wallpaperId[0]['src']);
            if (!empty($this->file)){
                $this->makeResizeDirectory(['full_size']);
                $this->putFileToTempDirectory($this->posterWallpaperId);
                $this->saveFullSize($this->posterWallpaperId);
            }
        }
    }
    private function compressCharactersPoster()
    {
        if ($posterIdArr = $this->posterCharactersId){
            $this->dirPosterName = 'characters_posters';
            $dirResizeThumbsArr = ['dir'=>'small','width'=>300];
            $charactersIdArr = $this->modelPoster::where('id_movie',$this->movieId)->whereIn('id',$posterIdArr)->get('src')->toArray();
            if (!empty($charactersIdArr)){
                $this->makeResizeDirectory(['small','full_size']);
                foreach ($charactersIdArr as $k => $id){
                    $fileName = $k+1;
                    $this->getFileFromSrc($id['src']);
                    if (!empty($this->file)){
                        $this->putFileToTempDirectory($fileName);
                        $this->saveThumbnails($dirResizeThumbsArr,$fileName);
                        $this->saveFullSize($fileName);
                    }
                }
            }
        }
    }
    private function compressAlternativePoster()
    {
        if ($posterIdArr = $this->posterAlternativeId){
            $this->dirPosterName = 'alt_posters';
            $dirResizeThumbsArr = ['dir'=>'small','width'=>300];
            $altIdArr = $this->modelPoster::where('id_movie',$this->movieId)->whereIn('id',$posterIdArr)->get('src')->toArray();
            if (!empty($altIdArr)){
                $this->makeResizeDirectory(['small','full_size']);
                foreach ($altIdArr as $k => $id){
                    $fileName = $k+1;
                    $this->getFileFromSrc($id['src']);
                    if (!empty($this->file)){
                        $this->putFileToTempDirectory($fileName);
                        $this->saveThumbnails($dirResizeThumbsArr,$fileName);
                        $this->saveFullSize($fileName);
                    }
                }
            }
        }
    }
    private function compressOriginalPoster()
    {
        $originalId = $this->modelPoster::where('id',$this->posterOriginalId)->where('id_movie',$this->movieId)->get('src')->toArray();
        $this->dirPosterName = 'original_poster';
        $dirResizeThumbsArr = [['dir'=>'thumb','width'=>100],['dir'=>'small','width'=>200],['dir'=>'medium','width'=>400]];
        if (!empty($originalId)){
            $this->makeResizeDirectory(['thumb','small','medium','full_size']);
            $this->getFileFromSrc($originalId[0]['src']);
            if (!empty($this->file)){
                $this->putFileToTempDirectory($this->posterOriginalId);
                foreach ($dirResizeThumbsArr as $item){
                    $this->saveThumbnails($item,$this->posterOriginalId);
                }
                $this->saveFullSize($this->posterOriginalId);
            }
        }
    }
    private function compressRussianPoster(){
        $russianId = $this->modelPoster::where('id',$this->posterRussianId)->where('id_movie',$this->movieId)->get('src')->toArray();
        $this->dirPosterName = 'russian_poster';
        $dirResizeThumbsArr = [['dir'=>'thumb','width'=>100],['dir'=>'small','width'=>200],['dir'=>'medium','width'=>400]];
        if (!empty($russianId)){
            $this->getFileFromSrc($russianId[0]['src']);
            if (!empty($this->file)){
                $this->makeResizeDirectory(['thumb','small','medium','full_size']);
                $this->putFileToTempDirectory($this->posterRussianId);
                foreach ($dirResizeThumbsArr as $item){
                    $this->saveThumbnails($item,$this->posterRussianId);
                }
                $this->saveFullSize($this->posterRussianId);
            }
        }
    }
    private function saveFullSize($fileName)
    {
        $fullSize = Image::read(Storage::disk('images')->get("/temp/{$this->movieId}"."/{$this->dirPosterName}/{$fileName}.jpg"));
        $width = $fullSize->width();
        $fileSize = $fullSize->exif('FILE.FileSize');
        $quality = $fileSize > 1024 ? 50 : 0;
        if ($width > 2000) {
            $fullSize->scale(width: 2000);
            if ($width > 3000) {
                $quality = 40;
            }
        }
        $fullSize->save(Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/full_size/{$fileName}.jpg"),quality: $quality);
    }
    private function saveThumbnails($params,$fileName)
    {
        $image = Image::read(Storage::disk('images')->get("/temp/{$this->movieId}"."/{$this->dirPosterName}/{$fileName}.jpg"));
        $image->scale(width: $params['width']);
        $image->save(Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/{$params['dir']}/{$fileName}.jpg"));
    }
    private function saveImages($fileName){
        $small = Image::read(Storage::disk('images')->get("/temp/{$this->movieId}"."/{$this->dirPosterName}/{$fileName}.jpg"));
        $height = $small->height();
        $width = $small->width();
        $fileSize = $small->exif('FILE.FileSize');
        if ($width > $height && $width > 1000) {
            $quality = $fileSize > 1024 ? 50 : 0;
            $small->cover(300, 200, 'center');
            $small->save(Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/small/{$fileName}.jpg"),quality:70);
            $fullSize = Image::read(Storage::disk('images')->get("/temp/{$this->movieId}/images/{$fileName}.jpg"));
            if ($width > 3000) {
                $fullSize->scale(3000);
                if ($width > 5000) {
                    $quality = 35;
                }
            }
            $fullSize->save(Storage::disk('images')->path("/resized/{$this->movieType}/{$this->movieId}/images/full_size/{$fileName}.jpg"),quality: $quality);
        }
    }
    private function putFileToTempDirectory($imageId)
    {
        Storage::disk('images')->put("/temp/{$this->movieId}/{$this->dirPosterName}/{$imageId}.jpg", $this->file);
    }
    private function makeResizeDirectory($dirSizeName)
    {
        foreach ($dirSizeName as $sizeName){
            Storage::disk('images')->makeDirectory("/resized/{$this->movieType}/{$this->movieId}/{$this->dirPosterName}/{$sizeName}/");
        }
    }
    private function getFileFromSrc($url)
    {
        $opts = ['http' => ['follow_location' => 1,]];
        $context = stream_context_create($opts);
        $urlHeaders = @get_headers($url);
        try {
            if (strpos($urlHeaders[0], '200')){
                $this->file = file_get_contents($url, false, $context);
            } else {
                $this->file = null;
                Log::info("NOT-FOUNT--ID--".$this->movieId." ---> SRC: ".$url);
            }
        } catch (\Exception $e) {
            //echo $e->getMessage();
            Log::info("EXEPTION--ID--".$this->movieId." ---> SRC: ".$url);
        }
    }
    private function deleteTempDirectory()
    {
        Storage::disk('images')->deleteDirectory('/temp');
    }
}
