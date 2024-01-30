<?php
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
