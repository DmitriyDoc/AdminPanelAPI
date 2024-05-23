import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref }  from "vue";


export const useCollectionsStore = defineStore('collectionsStore',() => {
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
    const collectionsData = ref([]);
    const route = useRoute();

    const getDataCollections = async () =>{
        try {
            axios.get('/api/collections/'
                + route.params.slug
                + '/'
                + route.params.collName
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                //+ '&search=' + state.value.searchQuery
            ).then((response) => {
                collectionsData.value = response.data;
                totalCount.value = response.data.total;
                loader.value = false;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const removeItemFromCollection = async (id,index) => {
        try {

            axios.delete('/api/collections',{ data: {
                    id_movie: id,
                    value: route.params.collName,
                }}
            ).then((response) => {
                collectionsData.value.data.splice(index,1);
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
        console.log(param);
        state.value.page = param;
        console.log(state.value.page );
    }
    const updatePageSize = (param) => {
        state.value.limit = param;
    }

    return {
        optionsCats,
        collectionsData,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        loader,
        removeItemFromCollection,
        getDataCollections,
        //updateSearchQuery,
        updateSpin,
        updateSort,
        updateCurrentPage,
        updatePageSize,
    }
});
