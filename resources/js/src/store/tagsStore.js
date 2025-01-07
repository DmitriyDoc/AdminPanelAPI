import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref }  from "vue";


export const useTagsStore = defineStore('tagsStore',() => {
    const state = ref({
        searchQuery: '',
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
    const tagsData = ref([]);
    const tagsList = ref([]);
    const route = useRoute();

    const getDataTags = async () =>{
        try {
            axios.get('/tag/'
                + route.params.tagName
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                + '&search=' + state.value.searchQuery
            ).then((response) => {
                tagsData.value = response.data;
                loader.value = false;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const getListTags = async () =>{
        try {
            axios.get('/tags'
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                + '&search=' + state.value.searchQuery
            ).then((response) => {
                tagsList.value = response.data;
                loader.value = false;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    // const removeItemFromCollection = async (id,index) => {
    //     try {
    //         axios.delete('/collections/del',{ data: {
    //                 id: id,
    //             }}
    //         ).then((response) => {
    //             collectionsList.value.data.splice(index,1);
    //         });
    //     } catch (e) {
    //         console.log('error',e);
    //     }
    // }
    const updateSearchQuery = (q) => {
        state.value.searchQuery = q;
    }
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
        tagsData,
        tagsList,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        loader,
        //removeItemFromTags,
        getDataTags,
        getListTags,
        updateSearchQuery,
        updateSpin,
        updateSort,
        updateCurrentPage,
        updatePageSize,
    }
});
