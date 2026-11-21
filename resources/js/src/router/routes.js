import DashboardAdm from "@/pages/DashboardAdm.vue";
import ParserAdm from "@/pages/ParserAdm.vue";
import ExportAdm from "@/pages/ExportAdm.vue";
import MoviesInfoAdm from "@/pages/MoviesInfoAdm.vue";
import SectionsInfoAdm from "@/pages/SectionsInfoAdm.vue";
import CollectionsInfoAdm from "@/pages/CollectionsInfoAdm.vue";
import FranchisesInfoAdm from "@/pages/FranchisesInfoAdm.vue";
import PersonsInfoAdm from "@/pages/PersonsInfoAdm.vue";
import UpdateMovieAdm from "@/pages/UpdateMovieAdm.vue";
import UpdatePersonAdm from "@/pages/UpdatePersonAdm.vue";
import MovieDetailsAdm from "@/pages/MovieDetailsAdm.vue";
import PersonDetailsAdm from "@/pages/PersonDetailsAdm.vue";
import TagListAdm from "@/pages/TagListAdm.vue";
import TagsInfoAdm from "@/pages/TagsInfoAdm.vue";
import SectionsListAdm from "@/pages/SectionsListAdm.vue";
import LoginAdm from "@/pages/LoginAdm.vue";
import ChartsListAdm from "../pages/ChartsListAdm.vue";
import ChartMoviesAdm from "../pages/ChartMoviesAdm.vue";
import SectionsManagementAdm from "../pages/SectionsManagementAdm.vue";
import ImportAdm from "@/pages/ImportAdm.vue";




const routes = [
    {
        path: '/',
        name: 'login',
        component: () => import('../pages/LoginAdm.vue'),
        meta: {
            layout: 'sign_in',
            permissions: [],
        },
    },
    {
        path: '/users',
        name: 'users',
        component: () => import('../pages/Users.vue'),
        meta: {
            layout: 'full',
            permissions: ['users-all', 'users-view'],
        },
    },

    {
        path: '/roles',
        name: 'roles',
        component: () => import('../pages/Roles.vue'),
        meta: {
            layout: 'full',
            permissions: ['roles-all', 'roles-view'],
        },
    },

    {
        path: '/permissions',
        name: 'permissions',
        component: () => import('../pages/Permissions.vue'),
        meta: {
            layout: 'full',
            permissions: ['permissions-all', 'permissions-view'],
        },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/parser',
        name: 'parser',
        component: ParserAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/export',
        name: 'export',
        component: ExportAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/movies',
        name: 'movies',
        component: MoviesInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/persons',
        name: 'persons',
        component: PersonsInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/section/:slug',
        name: 'showSection',
        component: SectionsInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/section/:slug/:collName',
        name: 'showCollection',
        component: CollectionsInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/section/:slug/:collName/:franName',
        name: 'showFranchise',
        component: FranchisesInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/movies/show/:id',
        name: 'showMovie',
        component: MovieDetailsAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/movies/edit/:id',
        name: 'editMovie',
        component: UpdateMovieAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/persons/show/:id',
        name: 'showPerson',
        component: PersonDetailsAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/persons/edit/:id',
        name: 'editPerson',
        component: UpdatePersonAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/sections/',
        name: 'listSections',
        component: SectionsListAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/management/',
        name: 'catsManagement',
        component: SectionsManagementAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/tags/list',
        name: 'listTag',
        component: TagListAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/tag/:tagName',
        name: 'showTags',
        component: TagsInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/charts',
        name: 'charts',
        component: ChartsListAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/charts/:chart_id',
        name: 'chartMovies',
        component: ChartMoviesAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/import',
        name: 'import',
        component: ImportAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
];
export default routes;
