import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref }  from "vue";


export const useSectionStore = defineStore('sectionStore',() => {
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
    const sections = ref([]);
    const sectionsData = ref([]);
    const route = useRoute();

    const getSections = async () =>{
        try {
            axios.get('/categories/sections').then((response) => {
                sections.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const getDataSections = async () =>{
        try {
            axios.get('/sections/'
                + route.params.slug
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                + '&search=' + state.value.searchQuery
            ).then((response) => {
                sectionsData.value = response.data;
                totalCount.value = response.data.total;
                loader.value = false;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const removeItemFromSection = async (id,index) => {
        try {
            axios.delete('/sections',{ data: {
                    id_movie: id,
                }}
            ).then((response) => {
                sectionsData.value.data.splice(index,1);
            });
        } catch (e) {
            console.log('error',e);
        }
    }
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
        sections,
        sectionsData,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        loader,
        removeItemFromSection,
        getSections,
        getDataSections,
        updateSearchQuery,
        updateSpin,
        updateSort,
        updateCurrentPage,
        updatePageSize,
    }
});
