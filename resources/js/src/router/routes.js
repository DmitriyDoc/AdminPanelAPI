import DashboardAdm from "@/pages/DashboardAdm.vue";
import MoviesInfoAdm from "@/pages/MoviesInfoAdm.vue";
import SectionsInfoAdm from "@/pages/SectionsInfoAdm.vue";
import CollectionsInfoAdm from "@/pages/CollectionsInfoAdm.vue";
import FranchisesInfoAdm from "@/pages/FranchisesInfoAdm.vue";
import PersonsInfoAdm from "@/pages/PersonsInfoAdm.vue";
import UpdateMovieAdm from "@/pages/UpdateMovieAdm.vue";
import UpdatePersonAdm from "@/pages/UpdatePersonAdm.vue";
import MovieDetailsAdm from "@/pages/MovieDetailsAdm.vue";
import PersonDetailsAdm from "@/pages/PersonDetailsAdm.vue";
import FranchiseAdm from "@/pages/FranchiseAdm.vue";

const routes = [
    {
        path: '/',
        component: DashboardAdm
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
        component: FranchiseAdm,
        name: 'addFranchise',
    },

];
export default routes;
