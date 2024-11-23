<?php

namespace App\Traits\Components;

use App\Http\Controllers\TranslatorController;
use App\Models\Tag;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait MoviesInfoTrait
{
    protected function getMoviesInfo($pages)
    {
        if (!empty($pages)) {
            foreach ($pages as $url => $page) {
                $document = new Document(trim($page));
                $this->logErrors($document,"div[class=error_code_404]"," 404-->>",$url);
                $this->logErrors($document,"div[class=errorPage__container]"," 500-->>",$url);
                if ($document->has('main h1')) {
                    //TITLE
                    $title = $document->find('h1');
                    $insertData['id_movie'] = get_id_from_url($url,self::ID_PATTERN)??null;
                    $insertData['title'] =  $title[0]->text()??null;
                    //ORIGINAL TITLE
                    $originalTitle = $title[0]->nextSibling('div')??null;
                    if (!empty($originalTitle)){
                        $originalTitle = trim(preg_replace(self::TITLE_PATTERN, '', $originalTitle->text()));
                    }
                    $insertData['original_title'] = $originalTitle??$title[0]->text()??null;
                    //TYPE,YEAR,RESTRICTIONS,RUNTIME
                    $infoContainer = $title[0]->nextSibling('ul')??null;
                    if (!empty($infoContainer)){
                        $infoCount = $infoContainer->count('li');
                        if (is_integer($infoCount)){
                            switch ($infoCount) {
                                case 4:
                                    //$insertData['type_film'] = $infoContainer->child(0)->text()??null;
                                    $insertData['year_release'] = (int)$infoContainer->child(1)->text()??null;
                                    $insertData['restrictions'] = $infoContainer->child(2)->text()??null;
                                    $insertData['runtime'] = $infoContainer->child(3)->text()??null;
                                    break;
                                case 3:
                                    if (is_numeric(get_id_from_url($infoContainer->child(0)->text(),self::YEAR_PATTERN))){
                                        $insertData['year_release'] = (int)$infoContainer->child(0)->text()??null;
                                        $insertData['restrictions'] = $infoContainer->child(1)->text()??null;
                                        $insertData['runtime'] = $infoContainer->child(2)->text()??null;
                                    }
                                    if (is_numeric(get_id_from_url($infoContainer->child(1)->text(),self::YEAR_PATTERN))){
                                        //$insertData['type_film'] = $infoContainer->child(0)->text()??null;
                                        $insertData['year_release'] = (int)$infoContainer->child(1)->text()??null;
                                    }
                                    break;
                                case 2:
                                    if (is_numeric(get_id_from_url($infoContainer->child(0)->text(),self::YEAR_PATTERN))){
                                        $insertData['year_release'] = (int)$infoContainer->child(0)->text()??null;
                                        $insertData['runtime'] = ctype_alnum($infoContainer->child(1)->text())?$infoContainer->child(1)->text():null;
                                    }
                                    if (is_numeric(get_id_from_url($infoContainer->child(1)->text(),self::YEAR_PATTERN))){
                                        //$insertData['type_film'] = $infoContainer->child(0)->text()??null;
                                        $insertData['year_release'] = (int)$infoContainer->child(1)->text()??null;
                                    }
                                    break;
                                case 1:
                                    if (is_numeric(get_id_from_url($infoContainer->child(0)->text(),self::YEAR_PATTERN))){
                                        $insertData['year_release'] = (int)$infoContainer->child(0)->text()??null;
                                    }
                                    break;

                            }
                        }

                    }

                    //RATING
                    if ($document->has("div[data-testid=hero-rating-bar__aggregate-rating__score]")){
                        $ratingContainer = $document->first("div[data-testid=hero-rating-bar__aggregate-rating__score]");
                        $rating = $ratingContainer->first('span')->text();
                        $insertData['rating'] = (float)$rating??null;
                    }
                    //GENRES
                    if ($document->has(".ipc-chip-list__scroller a")){
                        $genresContainer = $document->find(".ipc-chip-list__scroller a");
                        foreach ($genresContainer as $item){
                            $tag = $item->text();
                            $tagExist = DB::table('tags')->where('tag_name','=',$tag)->first();
                            if (!$tagExist) {
                                $translator = new TranslatorController();
                                $tagRus = $translator->translateTag($tag) ?? null;
                                Tag::updateOrCreate(
                                    ['value' => strtolower(str_ireplace(' ', '_',$tag))],
                                    ['tag_name' => $tag],
                                    ['tag_name_ru' => $tagRus],
                                );
                            }
                            $genresArray['genres'][] = $tag;
                            unset($tag);
                        }
                        $insertData['genres'] = serialize($genresArray)??null;
                        unset($genresArray);
                    }
                    //CAST,DIRECTORS,WRITERS
                    if ($document->has("section[data-testid=title-cast]")){
                        $castContainer = $document->first('section[data-testid=title-cast]');

                        if ($castContainer->has('div[data-testid=shoveler-items-container]')){
                            foreach ($castContainer->find('div[data-testid=shoveler-items-container]')[0]->children() as $item){
                                $role = '';
                                if ($item->lastChild()->has('div a[data-testid=cast-item-characters-link]')){
                                    $role = trim($item->lastChild()->find('div ul[data-testid=cast-item-characters-list]')[0]->text(),'…');
                                }
                                $castArray['cast'][get_id_from_url($item->lastChild()->find('a')[0]->getAttribute('href'),self::ACTOR_PATTERN)??''] = [$item->lastChild()->find('a')[0]->text()??'' => $role??''];
                            }
                            $insertData['cast'] = serialize($castArray)??null;
                            unset($castArray);
                        }

                        $crewContainer = $castContainer->lastChild();
                        unset($castContainer);
                        if (count($crewContainer->children()) > 2){
                            foreach ($crewContainer->children() as $item){
                                if (($item->child(0)->text() == 'Director')||($item->child(0)->text() == 'Directors')){
                                    foreach ($item->child(1)->find('ul')[0]->children() as $element){
                                        if (!empty($element->find('span')[0])){
                                            $span = $element->find('span')[0]->text()??'';
                                        }
                                        $directorsArray['directors'][get_id_from_url($element->find('a')[0]->getAttribute('href'),self::ACTOR_PATTERN)??''] = [$element->find('a')[0]->text() => $span??''];
                                    }
                                    $insertData['directors'] = serialize($directorsArray)??null;
                                    unset($span);
                                    unset($directorsArray);
                                }
                                if (($item->child(0)->text() == 'Writer')||($item->child(0)->text() == 'Writers')){
                                    foreach ($item->child(1)->find('ul')[0]->children() as $element){
                                        if (!empty($element->find('span')[0])){
                                            $span = $element->find('span')[0]->text()??'';
                                        }
                                        $writersArray['writers'][get_id_from_url($element->find('a')[0]->getAttribute('href'),self::ACTOR_PATTERN)??''] = [$element->find('a')[0]->text() => $span??''];
                                    }
                                    $insertData['writers'] = serialize($writersArray)??null;
                                    unset($span);
                                    unset($writersArray);
                                }
                            }

                        }

                    }
                    //STORY LINE
                    if ($document->has("span[data-testid=plot-l]")){
                        $storyContainer = $document->first('span[data-testid=plot-l]')->text();
                        if (!empty($storyContainer)){
                            $insertData['story_line'] = rtrim($storyContainer,'Read all')??null;
                        }

                    }
                    //RELEASE DATE
                    if ($document->has("li[data-testid=title-details-releasedate]")) {
                        $insertData['release_date'] = $document->first('li[data-testid=title-details-releasedate]')->find('ul li a')[0]->text()??null;
                    }
                    //COUNTRIES
                    if ($document->has("li[data-testid=title-details-origin]")){
                        foreach ($document->first('li[data-testid=title-details-origin]')->find('ul')[0]->children() as $item){
                            $countriesArray['countries'][] = $item->find('li a')[0]->text()??'';
                        }
                        $insertData['countries'] = serialize($countriesArray)??null;
                        unset($countriesArray);
                    }
                    //COMPANIES
                    if ($document->has("li[data-testid=title-details-companies]")){
                        foreach ($document->first('li[data-testid=title-details-companies]')->find('ul')[0]->children() as $item){
                            $companiesArray['companies'][] = $item->text()??'';
                        }
                        $insertData['companies'] = serialize($companiesArray)??null;
                        unset($companiesArray);
                    }
                    //BUDGET
                    if ($document->has("li[data-testid=title-boxoffice-budget]")){
                        $insertData['budget'] = get_id_from_url($document->first("li[data-testid=title-boxoffice-budget]")->lastChild()->text(),self::BUDGET_PATTERN)??null;
                    }
                    //Log::info('DATA--->>',[$insertData]);
                    $this->updateOrInsert($this->update_info_table,$insertData??[],$this->signByField);
                    unset($insertData);
                }
            }
        }
    }
}
