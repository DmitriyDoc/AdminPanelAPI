import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref } from "vue";
import {ElMessage} from "element-plus";
import { getActiveLanguage } from 'laravel-vue-i18n';

export const useMoviesStore = defineStore('moviesStore',() => {
    const state = ref({
        searchQuery: '',
        spinParam: 'desc',
        sortBy: 'id',
        page: 1,
        limit: 50,
    });
    const route = useRoute();
    const tableData = ref([]);
    const locale = ref([]);
    const singleData = ref({});
    const totalCount = ref(0);
    const pageSize = ref(state.value.limit);
    const currentPage = ref(state.value.page);
    const valueSort = ref(state.value.sortBy);
    const disabledBtnUpdate = ref(false);
    const disabledBtnSync = ref(false);
    const loader = ref(true);
    const error = ref();

    const getMovies = (type) =>{
        try {
            loader.value = true;
            axios.get('/movies/'
                + type
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                + '&search=' + state.value.searchQuery
            ).then((response) => {
                locale.value = response.data.locale;
                tableData.value = response.data.data;
                totalCount.value = response.data.total;
                loader.value = false;
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {

        }
    }
    const showItem = async () =>{
        if (Object.keys(route.params).length ){
            try {
                axios.get('/movies/show/' + route.params.id
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
    }
    const removeItem = async (id,type,index) => {
        axios.delete('/movies',{ data:
            {
                'id':id,
                'type':type,
            }
        }).then((response) => {
            tableData.value.data.splice(index,1);
            showItem();
        });
    }
    const updateItem = async (dataForm) => {
        const lang = getActiveLanguage();
        disabledBtnUpdate.value = true;
        axios.put('/movies/update',{ data:
            {
                'id':route.params.id,
                'form':dataForm,
                'lang':lang
            }
        }).then((response) => {
                if (response.data.success) {
                    showItem();
                    disabledBtnUpdate.value = false;
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
    const syncItem = async (params) => {
        disabledBtnSync.value = true;
        axios.post('/updatemovie',{ data: params}).then((response) => {
            if (response.status === 200) {
                showItem();
                disabledBtnSync.value = false;
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
        disabledBtnUpdate,
        disabledBtnSync,
        locale,
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
