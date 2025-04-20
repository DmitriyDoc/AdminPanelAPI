<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;

class LanguageController
{
    public function changeLocale(Request $request) : string
    {
        $locale = $request->query('lang','ru');
        if (! in_array($locale, ['en', 'ru'])) {
            abort(400);
        }
        if (session()->has('current_locale')) {
            session()->forget('current_locale');
        }
        session()->put('current_locale', $locale);
        app()->setLocale($locale);

        return session()->get('current_locale');
    }
    public static function localizingCollectionInfo() : array
    {
        return [
            'btn_add_new' => __('categories.btn_add_new'),
            'select_section' => __('categories.select_section'),
            'select' => __('categories.select'),
            'name_of_the_new_collection' => __('categories.name_of_the_new_collection'),
            'enter_name' => __('categories.enter_name'),
            'ru_name_of_the_new_collection' => __('categories.ru_name_of_the_new_collection'),
        ];
    }
    public static function localizingFranchiseInfo() : array
    {
        return [
            'btn_add_new' => __('categories.btn_add_new'),
            'select_collection' => __('categories.select_collection'),
            'name_of_the_new_franchise' => __('categories.name_of_the_new_franchise'),
            'select' => __('categories.select'),
            'enter_name' => __('categories.enter_name'),
            'ru_name_of_the_new_franchise' => __('categories.ru_name_of_the_new_franchise'),
        ];
    }
    public static function localizingParserInfo() : array
    {
        $localizingData['filter'] = __('sort.person_filters_fields');
        $localizingData['types'] = __('movies.type_movies');
        $localizingData['parser']['movies'] = __('parser.movies');
        $localizingData['parser']['persons'] = __('parser.persons');
        $localizingData['parser']['and_update_old'] = __('parser.and_update_old');
        $localizingData['parser']['parse_only_new_person'] = __('parser.parse_only_new_person');
        $localizingData['parser']['parser_settings'] = __('parser.parser_settings');
        $localizingData['parser']['add_movie_by_id'] = __('parser.add_movie_by_id');
        $localizingData['parser']['add_celebs_by_id'] = __('parser.add_celebs_by_id');
        $localizingData['parser']['parser_start'] = __('parser.parser_start');
        $localizingData['parser']['enter_movie_id'] = __('parser.enter_movie_id');
        $localizingData['parser']['input_movie_id'] = __('parser.input_movie_id');
        $localizingData['parser']['input_person_id'] = __('parser.input_person_id');
        $localizingData['parser']['enter_person_id'] = __('parser.enter_person_id');
        $localizingData['parser']['select_table_type'] = __('parser.select_table_type');
        $localizingData['parser']['select_table_for_this_movie'] = __('parser.select_table_for_this_movie');
        $localizingData['parser']['select_poster_type'] = __('parser.select_poster_type');
        $localizingData['parser']['choose_parser_type'] = __('parser.choose_parser_type');
        $localizingData['parser']['choose_movie_types'] = __('parser.choose_movie_types');
        $localizingData['parser']['select_sorting'] = __('parser.select_sorting');
        $localizingData['parser']['search_persons_filters'] = __('parser.search_persons_filters');
        $localizingData['parser']['select_filters'] = __('parser.select_filters');
        $localizingData['parser']['sort_by'] = __('parser.sort_by');
        $localizingData['parser']['sort_by_all'] = __('parser.sort_by_all');
        $localizingData['parser']['select_type_images'] = __('parser.select_type_images');
        $localizingData['parser']['select_type_posters'] = __('parser.select_type_posters');
        $localizingData['parser']['select_type'] = __('parser.select_type');
        $localizingData['parser']['parse_progress'] = __('parser.parse_progress');
        $localizingData['parser']['current_bar_progress'] = __('parser.current_bar_progress');
        $localizingData['parser']['finish_id_parse_by_date_period'] = __('parser.finish_id_parse_by_date_period');
        $localizingData['parser']['finish_parse_for_types'] = __('parser.finish_parse_for_types');
        $localizingData['parser']['parsed_and_localizing'] = __('parser.parsed_and_localizing');
        $localizingData['parser']['add_movie'] = __('buttons.add_movie');
        $localizingData['parser']['add_person'] = __('buttons.add_person');
        $localizingData['parser']['start'] = __('buttons.start');
        $localizingData['parser']['report'] = __('buttons.report');
        $localizingData['datepicker']['enabled'] = __('date_picker.enabled');
        $localizingData['datepicker']['disabled'] = __('date_picker.disabled');
        $localizingData['datepicker']['from'] = __('date_picker.from');
        $localizingData['datepicker']['till'] = __('date_picker.till');
        return $localizingData;
    }
    public static function localizingDashboardInfo() : array
    {
        return [
            'title' => __('dashboard.title'),
            'last_update' => __('dashboard.last_update'),
        ];
    }
    public static function localizingMoviesByTagsList() : array
    {
        return [
            'search_by_title'=> __('navigation.search_by_title'),
            'search_here'=> __('navigation.search_here'),
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'movies_sort_fields'=> __('sort.movies_sort_fields'),
            'created_at'=> __('table_fields.created_at'),
            'updated_at'=> __('table_fields.updated_at'),
            'actions'=> __('table_fields.actions'),
            'title'=> __('table_fields.title'),
            'id_movie'=> __('table_fields.id_movie'),
            'year'=> __('table_fields.year'),
        ];
    }
    public static function localizingFranchisesInfoList() : array
    {
        return [
            'search_by_resource_frinchise'=> __('navigation.search_by_resource_frinchise'),
            'search_here'=> __('navigation.search_here'),
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'id_frinchise'=> __('table_fields.id_frinchise'),
            'resource'=> __('table_fields.resource'),
            'created_at'=> __('table_fields.created_at'),
            'updated_at'=> __('table_fields.updated_at'),
            'collection_name'=> __('table_fields.collection_name'),
            'collection_name_rus'=> __('table_fields.collection_name_rus'),
            'frinchise_name'=> __('table_fields.frinchise_name'),
            'frinchise_name_rus'=> __('table_fields.frinchise_name_rus'),
            'actions'=> __('table_fields.actions'),
            'franchise_list_info_sort_fields'=> __('sort.franchise_list_info_sort_fields'),
        ];
    }
    public static function localizingCollectionsInfoList() : array
    {
        return [
            'search_by_resource_collection'=> __('navigation.search_by_resource_collection'),
            'search_here'=> __('navigation.search_here'),
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'created_at'=> __('table_fields.created_at'),
            'updated_at'=> __('table_fields.updated_at'),
            'resource'=> __('table_fields.resource'),
            'actions'=> __('table_fields.actions'),
            'id_category'=> __('table_fields.id_category'),
            'id_collection'=> __('table_fields.id_collection'),
            'collection_name'=> __('table_fields.collection_name'),
            'collection_name_rus'=> __('table_fields.collection_name_rus'),
            'collection_list_info_sort_fields'=> __('sort.collection_list_info_sort_fields'),
        ];
    }
    public static function localizingTagsInfoList() : array
    {
        return [
            'search_by_tag_name'=> __('navigation.search_by_tag_name'),
            'search_here'=> __('navigation.search_here'),
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'tag_sort_fields'=> __('sort.tag_sort_fields'),
            'created_at'=> __('table_fields.created_at'),
            'updated_at'=> __('table_fields.updated_at'),
            'tag_name'=> __('table_fields.tag_name'),
            'tag_name_rus'=> __('table_fields.tag_name_rus'),
            'resource'=> __('table_fields.resource'),
            'id_tag'=> __('table_fields.id_tag'),
        ];
    }
    public static function localizingPersonsList() : array
    {
        return [
            'search_by_name_id'=> __('navigation.search_by_name_id'),
            'search_here'=> __('navigation.search_here'),
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'person_sort_fields'=> __('sort.person_sort_fields'),
            'name'=> __('table_fields.name'),
            'id_person'=> __('table_fields.id_person'),
            'photo'=> __('table_fields.photo'),
            'created_at'=> __('table_fields.created_at'),
            'actions'=> __('table_fields.actions'),
        ];
    }
    public static function localizingSectionsList() : array
    {
        return [
            'section_sort_fields'=> __('sort.section_sort_fields'),
            'search_by_title_id'=> __('navigation.search_by_title_id'),
            'search_here'=> __('navigation.search_here'),
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'created_at'=> __('table_fields.created_at'),
            'updated_at'=> __('table_fields.updated_at'),
            'id_movie'=> __('table_fields.id_movie'),
            'poster'=> __('table_fields.poster'),
            'year'=> __('table_fields.year'),
            'collection'=> __('table_fields.collection'),
            'title'=> __('table_fields.title'),
            'actions'=> __('table_fields.actions'),
        ];
    }
    public static function localizingCollectionsList() : array
    {
        return [
            'reset'=> __('navigation.reset'),
            'go'=> __('navigation.go'),
            'search_here'=> __('navigation.search_here'),
            'collection_sort_fields'=> __('sort.collection_sort_fields'),
            'search_by_title_id'=> __('navigation.search_by_title_id'),
            'spin_by'=> __('navigation.spin_by'),
            'sort_by'=> __('navigation.sort_by'),
            'jump_to'=> __('navigation.jump_to'),
            'created_at'=> __('table_fields.created_at'),
            'updated_at'=> __('table_fields.updated_at'),
            'id_movie'=> __('table_fields.id_movie'),
            'poster'=> __('table_fields.poster'),
            'year'=> __('table_fields.year'),
            'franchise'=> __('table_fields.franchise'),
            'title'=> __('table_fields.title'),
            'actions'=> __('table_fields.actions'),
        ];
    }
    public static function localizingFranchisesList() : array
    {
        return [
            'display' => __('movies.display'),
            'display_timeline' => __('movies.display_timeline'),
            'display_table' => __('movies.display_table'),
            'details' => __('movies.details'),
            'timeline_franchise' => __('movies.timeline_franchise'),
            'timeline_movies' => __('movies.timeline_movies'),
            'reset' => __('navigation.reset'),
            'go' => __('navigation.go'),
            'search_here' => __('navigation.search_here'),
            'search_by_title_id' => __('navigation.search_by_title_id'),
            'created_at' => __('table_fields.created_at'),
            'updated_at' => __('table_fields.updated_at'),
            'id_movie' => __('table_fields.id_movie'),
            'poster'=> __('table_fields.poster'),
            'year' => __('table_fields.year'),
            'title' => __('table_fields.title'),
            'actions' => __('table_fields.actions'),
        ];
    }
    public static function localizingMoviesList() : array
    {
        return [
            'type_movies' => __('movies.type_movies'),
            'type_movies_title' => __('movies.type_movies_title'),
            'table_sort_fields' => __('sort.movies_sort_fields'),
            'reset' => __('navigation.reset'),
            'go' => __('navigation.go'),
            'search_here' => __('navigation.search_here'),
            'search_by_title_id' => __('navigation.search_by_title_id'),
            'spin_by' => __('navigation.spin_by'),
            'sort_by' => __('navigation.sort_by'),
            'jump_to' => __('navigation.jump_to'),
            'created_at' => __('table_fields.created_at'),
            'updated_at' => __('table_fields.updated_at'),
            'id_movie' => __('table_fields.id_movie'),
            'poster'=> __('table_fields.poster'),
            'year' => __('table_fields.year'),
            'title' => __('table_fields.title'),
            'actions' => __('table_fields.actions'),
        ];
    }
    public static function localizingMovieShow() : array
    {
        return [
            'edit' => __('buttons.edit'),
            'update' => __('buttons.update'),
            'sync_imdb' => __('buttons.sync_imdb'),
            'clear_selection' => __('buttons.clear_selection'),
            'delete_selection' => __('buttons.delete_selection'),
            'move_selection' => __('buttons.move_selection'),
            'next_page' => __('buttons.next_page'),
            'original' => __('buttons.original'),
            'russian' => __('buttons.russian'),
            'characters' => __('buttons.characters'),
            'alternative' => __('buttons.alternative'),
            'wallpaper' => __('buttons.wallpaper'),
            'unselected' => __('movies.unselected'),
            'year_release' => __('movies.year_release'),
            'original_title' => __('movies.original_title'),
            'title' => __('movies.title'),
            'release_date' => __('movies.release_date'),
            'restriction' => __('movies.restriction'),
            'runtime' => __('movies.runtime'),
            'rating' => __('movies.rating'),
            'budget' => __('movies.budget'),
            'empty' => __('movies.empty'),
            'genres' => __('movies.genres'),
            'countries' => __('movies.countries'),
            'companies' => __('movies.companies'),
            'directors' => __('movies.directors'),
            'writers' => __('movies.writers'),
            'cast' => __('movies.cast'),
            'story' => __('movies.story'),
            'images' => __('movies.images'),
            'posters' => __('movies.posters'),
            'video' => __('movies.video'),
            'poster_type' => __('movies.poster_type'),
            'sync_notice' => __('movies.sync_notice'),
            'check_viewed' => __('movies.check_viewed'),
            'check_short' => __('movies.check_short'),
            'check_type_content' => __('movies.check_type_content'),
            'assign_categories' => __('movies.assign_categories'),
            'select' => __('movies.select'),
            'viewed' => __('movies.viewed'),
            'short_film' => __('movies.short_film'),
            'adult' => __('movies.adult'),
            'assign_poster_as' => __('movies.assign_poster_as'),
            'no_assigned' => __('movies.no_assigned'),
            'photo' => __('table_fields.photo'),
            'link' => __('table_fields.link'),
            'assign_status' => __('table_fields.assign_status'),
        ];
    }
    public static function localizingPersonShow() : array
    {
        return [
            'edit' => __('buttons.edit'),
            'empty' => __('movies.empty'),
            'birthday' => __('persons_info.birthday'),
            'birthday_location' => __('persons_info.birthday_location'),
            'died' => __('persons_info.died'),
            'died_location' => __('persons_info.died_location'),
            'known_for' => __('persons_info.known_for'),
            'image_type' => __('persons_info.image_type'),
            'role' => __('table_fields.role'),
            'title' => __('table_fields.title'),
            'id_movie' => __('table_fields.id_movie'),
            'year' => __('table_fields.year'),
            'photo' => __('table_fields.photo'),
            'link' => __('table_fields.link'),
            'images' => __('movies.images'),
            'next_page' => __('buttons.next_page'),
            'sync_imdb' => __('buttons.sync_imdb'),
            'clear_selection' => __('buttons.clear_selection'),
            'delete_selection' => __('buttons.delete_selection'),
        ];
    }
}
