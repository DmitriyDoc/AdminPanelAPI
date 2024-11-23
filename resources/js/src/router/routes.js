import DashboardAdm from "@/pages/DashboardAdm.vue";
import ParserAdm from "@/pages/ParserAdm.vue";
import MoviesInfoAdm from "@/pages/MoviesInfoAdm.vue";
import SectionsInfoAdm from "@/pages/SectionsInfoAdm.vue";
import CollectionsInfoAdm from "@/pages/CollectionsInfoAdm.vue";
import FranchisesInfoAdm from "@/pages/FranchisesInfoAdm.vue";
import PersonsInfoAdm from "@/pages/PersonsInfoAdm.vue";
import UpdateMovieAdm from "@/pages/UpdateMovieAdm.vue";
import UpdatePersonAdm from "@/pages/UpdatePersonAdm.vue";
import MovieDetailsAdm from "@/pages/MovieDetailsAdm.vue";
import PersonDetailsAdm from "@/pages/PersonDetailsAdm.vue";
import FranchiseAddAdm from "@/pages/FranchiseAddAdm.vue";
import FranchiseListAdm from "@/pages/FranchiseListAdm.vue";
import CollectionListAdm from "@/pages/CollectionListAdm.vue";
import CollectionAddAdm from "@/pages/CollectionAddAdm.vue";
import TagListAdm from "@/pages/TagListAdm.vue";
import TagsInfoAdm from "@/pages/TagsInfoAdm.vue";
import LoginAdm from "@/pages/LoginAdm.vue";

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
        path: '/movies/:slug',
        name: 'movies',
        component: MoviesInfoAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/persons/:slug',
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
        path: '/movies/:slug/show/:id',
        name: 'showmovie',
        component: MovieDetailsAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/persons/:slug/show/:id',
        name: 'showperson',
        component: PersonDetailsAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/movies/:slug/edit/:id',
        name: 'editMovie',
        component: UpdateMovieAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/persons/:slug/edit/:id',
        name: 'editPerson',
        component: UpdatePersonAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/franchise/',
        name: 'addFranchise',
        component: FranchiseAddAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/franchise/list',
        name: 'listFranchise',
        component: FranchiseListAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/collection/',
        name: 'addCollection',
        component: CollectionAddAdm,
        meta: {
            layout: 'full',
            permissions: [],
        },
    },
    {
        path: '/categories/collection/list',
        name: 'listCollection',
        component: CollectionListAdm,
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

];
export default routes;
