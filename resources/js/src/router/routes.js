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

const routes = [
    {
        path: '/',
        component: DashboardAdm
    },
    {
        path: '/parser',
        component: ParserAdm
    },
    {
        path: '/movies/:slug',
        component: MoviesInfoAdm,
    },
    {
        path: '/persons/:slug',
        component: PersonsInfoAdm,
    },
    {
        path: '/section/:slug',
        component: SectionsInfoAdm,
    },
    {
        path: '/section/:slug/:collName',
        component: CollectionsInfoAdm,
        name: 'showCollection',
    },
    {
        path: '/section/:slug/:collName/:franName',
        component: FranchisesInfoAdm,
        name: 'showFranchise',
    },
    {
        path: '/movies/:slug/show/:id',
        component: MovieDetailsAdm,
        name: 'showmovie',
    },
    {
        path: '/persons/:slug/show/:id',
        component: PersonDetailsAdm,
        name: 'showperson',
    },
    {
        path: '/movies/:slug/edit/:id',
        component: UpdateMovieAdm,
        name: 'editMovie',
    },
    {
        path: '/persons/:slug/edit/:id',
        component: UpdatePersonAdm,
        name: 'editPerson',
    },
    {
        path: '/categories/franchise/',
        component: FranchiseAddAdm,
        name: 'addFranchise',
    },
    {
        path: '/categories/franchise/list',
        component: FranchiseListAdm,
        name: 'listFranchise',
    },
    {
        path: '/categories/collection/',
        component: CollectionAddAdm,
        name: 'addCollection',
    },
    {
        path: '/categories/collection/list',
        component: CollectionListAdm,
        name: 'listCollection',
    },

];
export default routes;
