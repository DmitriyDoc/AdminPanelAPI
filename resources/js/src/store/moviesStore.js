import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { onMounted, onUpdated, ref} from "vue";
import {ElMessage} from "element-plus";

export const useMoviesStore = defineStore('moviesStore',() => {
    const state = ref({
        searchQuery: '',
        spinParam: 'desc',
        sortBy: 'created_at',
        page: 1,
        limit: 50,
    });
    const route = useRoute();
    const tableData = ref([]);
    const singleData = ref({});
    const totalCount = ref(0);
    const pageSize = ref(state.value.limit);
    const currentPage = ref(state.value.page);
    const valueSort = ref(state.value.sortBy);
    const loader = ref(true);
    const error = ref();

    const getMovies = () =>{
        try {
            tableData.value = [];
            axios.get('/movies/'
                + route.params.slug
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                + '&search=' + state.value.searchQuery
            ).then((response) => {
                tableData.value = response.data.data;
                totalCount.value = response.data.total;
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {
            loader.value = false;
        }
    }
    const showItem = async () =>{
        try {
            axios.get('/movies/' + route.params.slug + '/show/' + route.params.id
            ).then((response) => {
                singleData.value = response.data;
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {
            loader.value = false;
        }
    }
    const removeItem = async (id,index) => {
        axios.delete('/movies/' + route.params.slug + '/' + id).then((response) => {
            tableData.value.splice(index,1);
            showItem();
        });
    }
    const updateItem = async (dataForm) => {
        axios.patch('/movies/' + route.params.slug + '/update/' + route.params.id,{ data: dataForm })
            .then((response) => {
                if (response.status === 200) {
                    showItem();
                    ElMessage({
                        type: 'success',
                        message: 'Update completed',
                    })
                } else {
                    ElMessage({
                        type: 'error',
                        message: 'Update error',
                    })
                }
        });
    }
    const syncItem = async (posterType) => {
        axios.put('/updatemovie',{ data: {
            id: singleData.value.id_movie??route.params.id,
            type: route.params.slug,
            posterType: posterType,
        }}).then((response) => {
            if (response.status === 200) {
                showItem();
                ElMessage({
                    type: 'success',
                    message: 'Sync with IMDB completed',
                })
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Sync with IMDB  is not finished',
                });
            }
        });
    }
    state.value.searchQuery = '';

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
        tableData,
        singleData,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        loader,
        error,
        syncItem,
        updateItem,
        showItem,
        removeItem,
        updatePageSize,
        updateCurrentPage,
        updateSort,
        updateSpin,
        updateSearchQuery,
        getMovies,
    }
});
