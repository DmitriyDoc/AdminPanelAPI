import DashboardAdm from "@/pages/DashboardAdm.vue";
import MoviesInfoAdm from "@/pages/MoviesInfoAdm.vue";
import PersonsInfoAdm from "@/pages/PersonsInfoAdm.vue";
import UpdateMovieAdm from "@/pages/UpdateMovieAdm.vue";
import MovieDetailsAdm from "@/pages/MovieDetailsAdm.vue";
import PersonDetailsAdm from "@/pages/PersonDetailsAdm.vue";

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
        path: '/movies/:slug/show/:id',
        component: MovieDetailsAdm,
        name: 'showmovie',
    },
    {
        path: '/persons/:slug/show/:id',
        component: PersonDetailsAdm,
        name: 'showperson',
    },
    // {
    //     path: '/movies/:slug/edit/:id',
    //     component: UpdateMovieAdm,
    //     name: 'edit',
    // },

];
export default routes;
