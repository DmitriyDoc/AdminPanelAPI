<?php

use \Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

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
if (!function_exists('get_id_from_url')) {
    function get_id_from_url($url,$mask) {
        if (!empty($url)){
            preg_match($mask, $url, $matches);
            return $matches[0]??null;
        }
        return null;
    }
}

if (!function_exists('clear_string_from_digits')) {
    function clear_string_from_digits($url,$mask) {
        if (!empty($url)){
            return preg_replace($mask, '', $url)??null;
        }
        return null;
    }
}

if (!function_exists('unset_serialize_key')) {
    function unset_serialize_key($array) {
        if (!empty($array)){
            foreach ($array as $item) {
                return $item;
            }
        }
        return [];
    }
}
if (!function_exists('modelByName')) {
    function modelByName($modelName)
    {
        $modelNameWithNameSpace = "App\Models" . '\\'.$modelName;
        return app($modelNameWithNameSpace);
    }
}
if (!function_exists('convertVariableToModelName')) {
    function convertVariableToModelName($typeSegment,$nameSegment,$nameSpace='App'){
        if (empty($nameSpace) || is_null($nameSpace) || $nameSpace === "")
        {
            $modelNameWithNameSpace = "App".'\\'.$typeSegment.$nameSegment;
            return app($modelNameWithNameSpace);
        }

        if (is_array($nameSpace))
        {
            $nameSpace = implode('\\', $nameSpace);
            $modelNameWithNameSpace = $nameSpace.'\\'.$typeSegment.$nameSegment;
            return app($modelNameWithNameSpace);
        }elseif (!is_array($nameSpace))
        {
            $modelNameWithNameSpace = $nameSpace.'\\'.$typeSegment.$nameSegment;
            return app($modelNameWithNameSpace);
        }
    }

}

if (!function_exists('transaction')) {
    function transaction(\Closure $callback, int $attempts = 1)
    {
        if (DB::transactionLevel()){
            return $callback();
        }
        return DB::transaction($callback,$attempts);
    }
}
if (!function_exists('cascaderStructure')) {
    function cascaderStructure($collectionArray)
    {
        $localizingFranchiseModel = \App\Models\LocalizingFranchise::query()->get();
        $currentLocaleLabel = 'label_'.Lang::locale();
        $currentLocaleTitle = 'title_'.Lang::locale();
        if ( !empty($collectionArray) ){
            foreach ( $collectionArray as $catKey => $catValue ){
                $collectionArray[$catKey]['label'] = $catValue[$currentLocaleTitle];
                $collectionArray[$catKey]['value'] = $catValue['id'];
                $collectionArray[$catKey]['disabled'] = true;
                unset($collectionArray[$catKey]['id']);
                unset($collectionArray[$catKey]['title']);
                foreach ( $catValue['children'] as $colKey => $colValue ){
                    $collectionArray[$catKey]['children'][$colKey]['label'] = $colValue[$currentLocaleLabel];
                    $collectionArray[$catKey]['children'][$colKey]['value'] = $colValue['id'];
                    unset($collectionArray[$catKey]['children'][$colKey]['id']);
                    unset($collectionArray[$catKey]['children'][$colKey]['category_id']);
                    if (!empty($colValue['children'])){
                        foreach ($colValue['children'] as $frhKey => $frValue){
                            $collectionArray[$catKey]['children'][$colKey]['children'][$frhKey]['value'] = 'fr_'.$colValue['id'].$frValue['id'];
                            $collectionArray[$catKey]['children'][$colKey]['children'][$frhKey]['label'] = $localizingFranchiseModel->find($frValue['id'])->$currentLocaleLabel??'empty label';
                            unset($collectionArray[$catKey]['children'][$colKey]['children'][$frhKey]['id']);
                            unset($collectionArray[$catKey]['children'][$colKey]['children'][$frhKey]['collection_id']);
                        }
                    }
                }
            }
        }
        return $collectionArray ?? '';
    }
    if (!function_exists('getTableSegmentOrTypeId')) {
        function getTableSegmentOrTypeId(mixed $segment)
        {
            $allowedTableNames = [
                1=>'FeatureFilm',
                2=>'MiniSeries',
                3=>'ShortFilm',
                4=>'TvMovie',
                5=>'TvSeries',
                6=>'TvShort',
                7=>'TvSpecial',
                8=>'Video',
            ];
            if (is_string($segment)){
                return array_search($segment, $allowedTableNames);
            }
            if (is_numeric($segment)){
                return $allowedTableNames[$segment];
            }
        }
    }
    if (!function_exists('camelToSnake')) {
        function camelToSnake($input) {
            $words = preg_split('/(?=[A-Z])/', $input, -1, PREG_SPLIT_NO_EMPTY);
            $snakeCase = implode('_', $words);
            $snakeCase = strtolower($snakeCase);

            return $snakeCase;
        }
    }
    if (!function_exists('snakeToCamel')) {
        function snakeToCamel($string, $capitalizeFirstCharacter = false)
        {
            $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
            if (!$capitalizeFirstCharacter) {
                $str[0] = strtolower($str[0]);
            }
            return $str;

        }
    }
    if (!function_exists('transformTitleByLocale')) {
        function transformTitleByLocale()
        {
            if (Lang::locale() == 'ru'){
                return 'title';
            } elseif (Lang::locale() == 'en') {
                return 'original_title';
            }
        }
    }
    if (!function_exists('parseHttpHeaders')) {
        function parseHttpHeaders( $headers )
        {
            $head = [];
            foreach( $headers as $k => $v )
            {
                $t = explode( ':', $v, 2 );
                if( isset( $t[1] ) )
                    $head[ trim($t[0]) ] = trim( $t[1] );
                else
                {
                    $head[] = $v;
                    if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
                        $head['response_code'] = intval($out[1]);
                }
            }
            return $head;
        }
    }
}
