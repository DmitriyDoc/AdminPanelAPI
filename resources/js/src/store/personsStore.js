import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref } from "vue";
import {ElMessage} from "element-plus";

export const usePersonsStore = defineStore('personsStore',() => {
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
    const disabledBtnSync = ref(false);
    const loader = ref(true);
    const error = ref();

    const getCelebs = async () =>{
        try {
            loader.value = true;
            axios.get('/persons'
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
        try {
            axios.get('/persons/show/' + route.params.id
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
        axios.delete('/persons',{
            data: {
                id: id
            }
        }).then((response) => {
            if (response.status === 200) {
                tableData.value.data.splice(index,1);
                getCelebs();
                ElMessage({
                    type: 'success',
                    message: 'Selected person(s) was be removed successfully',
                })
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Remove is not finished',
                });
            }

        });
    }
    const removeFilmographyItems = async (idArray,activeTab) => {
        axios.patch('/persons/remove_items',{ data: {
                id: singleData.value.id_celeb,
                tab_index: activeTab,
                id_items: idArray,
            }}).then((response) => {
            if (response.status === 200) {
                showItem();
                ElMessage({
                    type: 'success',
                    message: 'Selected item(s) was removed',
                })
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Remove item(s)  is not finished',
                });
            }
        });
    }
    const syncItem = async (imageType) => {
        disabledBtnSync.value = true;
        axios.post('/updateceleb',{ data: {
            id:singleData.value.id_celeb,
            type:'Celebs',
            imageType: imageType,
        }}).then((response) => {
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
    getCelebs();

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
        disabledBtnSync,
        locale,
        route,
        loader,
        error,
        syncItem,
        showItem,
        removeItem,
        removeFilmographyItems,
        updatePageSize,
        updateCurrentPage,
        updateSort,
        updateSpin,
        updateSearchQuery,
        getCelebs,
    }
});
