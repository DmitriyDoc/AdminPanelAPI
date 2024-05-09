import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref }  from "vue";


export const useFranchiseStore = defineStore('franchiseStore',() => {
    const state = ref({
        //searchQuery: '',
        spinParam: 'desc',
        sortBy: 'created_at',
        page: 1,
        limit: 50,
    });
    const totalCount = ref(0);
    const pageSize = ref(state.value.limit);
    const currentPage = ref(state.value.page);
    const valueSort = ref(state.value.sortBy);
    const loader = ref(true);
    const optionsCats = ref([]);
    const franchiseData = ref([]);
    const route = useRoute();

    const getDataFranchise = async () =>{
        try {
            axios.get('/api/franchise/'
                + route.params.slug
                + '/'
                + route.params.franName
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                //+ '&search=' + state.value.searchQuery
            ).then((response) => {
                franchiseData.value = response.data;
                totalCount.value = response.data.total;
                loader.value = false;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const removeItemFromFranchise = async (id,index) => {
        try {

            axios.delete('/api/franchise',{ data: {
                    id_movie: id,
                    value: route.params.franName,
                }}
            ).then((response) => {
                franchiseData.value.data.splice(index,1);
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    // const updateSearchQuery = (q) => {
    //     state.value.searchQuery = q;
    // }
    const updateSpin = (param) => {
        state.value.spinParam = param;
    }
    const updateSort = (param) => {
        state.value.sortBy = param;
    }
    const updateCurrentPage = (param) => {
        state.value.page = param;
    }
    const updatePageSize = (param) => {
        state.value.limit = param;
    }

    return {
        optionsCats,
        franchiseData,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        loader,
        removeItemFromFranchise,
        getDataFranchise,
        //updateSearchQuery,
        updateSpin,
        updateSort,
        updateCurrentPage,
        updatePageSize,
    }
});
