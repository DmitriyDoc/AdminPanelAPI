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
        $localizingData['title'] = __('dashboard.title');
        $localizingData['last_update'] = __('dashboard.last_update');
        return $localizingData;
    }
    public static function localizingMoviesByTagsList() : array
    {
        $localizingData['search_by_title'] = __('navigation.search_by_title');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        $localizingData['movies_sort_fields'] = __('sort.movies_sort_fields');
        $localizingData['created_at'] = __('table_fields.created_at');
        $localizingData['updated_at'] = __('table_fields.updated_at');
        $localizingData['actions'] = __('table_fields.actions');
        $localizingData['title'] = __('table_fields.title');
        $localizingData['id_movie'] = __('table_fields.id_movie');
        $localizingData['year'] = __('table_fields.year');
        return $localizingData;
    }
    public static function localizingFranchisesInfoList() : array
    {
        $localizingData['search_by_resource_frinchise'] = __('navigation.search_by_resource_frinchise');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        $localizingData['id_frinchise'] = __('table_fields.id_frinchise');
        $localizingData['resource'] = __('table_fields.resource');
        $localizingData['created_at'] = __('table_fields.created_at');
        $localizingData['updated_at'] = __('table_fields.updated_at');
        $localizingData['collection_name'] = __('table_fields.collection_name');
        $localizingData['collection_name_rus'] = __('table_fields.collection_name_rus');
        $localizingData['frinchise_name'] = __('table_fields.frinchise_name');
        $localizingData['frinchise_name_rus'] = __('table_fields.frinchise_name_rus');
        $localizingData['actions'] = __('table_fields.actions');
        $localizingData['franchise_list_info_sort_fields'] = __('sort.franchise_list_info_sort_fields');
        return $localizingData;
    }
    public static function localizingCollectionsInfoList() : array
    {
        $localizingData['search_by_resource_collection'] = __('navigation.search_by_resource_collection');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        $localizingData['created_at'] = __('table_fields.created_at');
        $localizingData['updated_at'] = __('table_fields.updated_at');
        $localizingData['resource'] = __('table_fields.resource');
        $localizingData['actions'] = __('table_fields.actions');
        $localizingData['id_category'] = __('table_fields.id_category');
        $localizingData['id_collection'] = __('table_fields.id_collection');
        $localizingData['collection_name'] = __('table_fields.collection_name');
        $localizingData['collection_name_rus'] = __('table_fields.collection_name_rus');
        $localizingData['collection_list_info_sort_fields'] = __('sort.collection_list_info_sort_fields');
        return $localizingData;
    }
    public static function localizingTagsInfoList() : array
    {
        $localizingData['search_by_tag_name'] = __('navigation.search_by_tag_name');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        $localizingData['tag_sort_fields'] = __('sort.tag_sort_fields');
        $localizingData['created_at'] = __('table_fields.created_at');
        $localizingData['updated_at'] = __('table_fields.updated_at');
        $localizingData['tag_name'] = __('table_fields.tag_name');
        $localizingData['tag_name_rus'] = __('table_fields.tag_name_rus');
        $localizingData['resource'] = __('table_fields.resource');
        $localizingData['id_tag'] = __('table_fields.id_tag');
        return $localizingData;
    }
    public static function localizingPersonsList() : array
    {
        $localizingData['search_by_name_id'] = __('navigation.search_by_name_id');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        $localizingData['person_sort_fields'] = __('sort.person_sort_fields');
        $localizingData['name'] = __('table_fields.name');
        $localizingData['id_person'] = __('table_fields.id_person');
        $localizingData['photo'] = __('table_fields.photo');
        $localizingData['created_at'] = __('table_fields.created_at');
        $localizingData['actions'] = __('table_fields.actions');
        return $localizingData;
    }
    public static function localizingSectionsList() : array
    {
        $localizingData['section_sort_fields'] = __('sort.section_sort_fields');
        $localizingData['search_by_title_id'] = __('navigation.search_by_title_id');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        return $localizingData;
    }
    public static function localizingCollectionsList() : array
    {
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['collection_sort_fields'] = __('sort.collection_sort_fields');
        $localizingData['search_by_title_id'] = __('navigation.search_by_title_id');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        return $localizingData;
    }
    public static function localizingFranchisesList() : array
    {
        $localizingData['display'] = __('movies.display');
        $localizingData['display_timeline'] = __('movies.display_timeline');
        $localizingData['display_table'] = __('movies.display_table');
        $localizingData['details'] = __('movies.details');
        $localizingData['timeline_franchise'] = __('movies.timeline_franchise');
        $localizingData['timeline_movies'] = __('movies.timeline_movies');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['search_by_title_id'] = __('navigation.search_by_title_id');
        return $localizingData;
    }
    public static function localizingMoviesList() : array
    {
        $localizingData['type_movies'] = __('movies.type_movies');
        $localizingData['type_movies_title'] = __('movies.type_movies_title');
        $localizingData['table_sort_fields'] = __('sort.movies_sort_fields');
        $localizingData['reset'] = __('navigation.reset');
        $localizingData['go'] = __('navigation.go');
        $localizingData['search_here'] = __('navigation.search_here');
        $localizingData['search_by_title_id'] = __('navigation.search_by_title_id');
        $localizingData['spin_by'] = __('navigation.spin_by');
        $localizingData['sort_by'] = __('navigation.sort_by');
        $localizingData['jump_to'] = __('navigation.jump_to');
        return $localizingData;
    }
    public static function localizingMovieShow() : array
    {
        $localizingData['edit'] = __('buttons.edit');
        $localizingData['update'] = __('buttons.update');
        $localizingData['sync_imdb'] = __('buttons.sync_imdb');
        $localizingData['clear_selection'] = __('buttons.clear_selection');
        $localizingData['delete_selection'] = __('buttons.delete_selection');
        $localizingData['next_page'] = __('buttons.next_page');
        $localizingData['original'] = __('buttons.original');
        $localizingData['russian'] = __('buttons.russian');
        $localizingData['characters'] = __('buttons.characters');
        $localizingData['alternative'] = __('buttons.alternative');
        $localizingData['wallpaper'] = __('buttons.wallpaper');
        $localizingData['unselected'] = __('movies.unselected');
        $localizingData['year_release'] = __('movies.year_release');
        $localizingData['original_title'] = __('movies.original_title');
        $localizingData['title'] = __('movies.title');
        $localizingData['release_date'] = __('movies.release_date');
        $localizingData['restriction'] = __('movies.restriction');
        $localizingData['runtime'] = __('movies.runtime');
        $localizingData['rating'] = __('movies.rating');
        $localizingData['budget'] = __('movies.budget');
        $localizingData['empty'] = __('movies.empty');
        $localizingData['genres'] = __('movies.genres');
        $localizingData['countries'] = __('movies.countries');
        $localizingData['companies'] = __('movies.companies');
        $localizingData['directors'] = __('movies.directors');
        $localizingData['writers'] = __('movies.writers');
        $localizingData['cast'] = __('movies.cast');
        $localizingData['story'] = __('movies.story');
        $localizingData['images'] = __('movies.images');
        $localizingData['posters'] = __('movies.posters');
        $localizingData['video'] = __('movies.video');
        $localizingData['poster_type'] = __('movies.poster_type');
        $localizingData['sync_notice'] = __('movies.sync_notice');
        $localizingData['check_viewed'] = __('movies.check_viewed');
        $localizingData['check_short'] = __('movies.check_short');
        $localizingData['check_type_content'] = __('movies.check_type_content');
        $localizingData['assign_categories'] = __('movies.assign_categories');
        $localizingData['select'] = __('movies.select');
        $localizingData['viewed'] = __('movies.viewed');
        $localizingData['short_film'] = __('movies.short_film');
        $localizingData['adult'] = __('movies.adult');
        $localizingData['assign_poster_as'] = __('movies.assign_poster_as');
        $localizingData['no_assigned'] = __('movies.no_assigned');
        $localizingData['photo'] = __('table_fields.photo');
        $localizingData['link'] = __('table_fields.link');
        $localizingData['assign_status'] = __('table_fields.assign_status');
        return $localizingData;
    }
    public static function localizingPersonShow() : array
    {
        $localizingData['edit'] = __('buttons.edit');
        $localizingData['empty'] = __('movies.empty');
        $localizingData['birthday'] = __('persons_info.birthday');
        $localizingData['birthday_location'] = __('persons_info.birthday_location');
        $localizingData['died'] = __('persons_info.died');
        $localizingData['died_location'] = __('persons_info.died_location');
        $localizingData['known_for'] = __('persons_info.known_for');
        $localizingData['image_type'] = __('persons_info.image_type');
        $localizingData['role'] = __('table_fields.role');
        $localizingData['title'] = __('table_fields.title');
        $localizingData['id_movie'] = __('table_fields.id_movie');
        $localizingData['year'] = __('table_fields.year');
        $localizingData['photo'] = __('table_fields.photo');
        $localizingData['link'] = __('table_fields.link');
        $localizingData['images'] = __('movies.images');
        $localizingData['next_page'] = __('buttons.next_page');
        $localizingData['sync_imdb'] = __('buttons.sync_imdb');
        $localizingData['clear_selection'] = __('buttons.clear_selection');
        $localizingData['delete_selection'] = __('buttons.delete_selection');
        return $localizingData;
    }
}
