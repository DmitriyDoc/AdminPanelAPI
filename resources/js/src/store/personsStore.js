import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { onMounted, onUpdated, ref} from "vue";

export const usePersonsStore = defineStore('personsStore',() => {
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

    const getCelebs = async () =>{
        try {
            axios.get('/api/persons/'
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
            axios.get('/api/persons/' + route.params.slug + '/show/' + route.params.id
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
        axios.delete('/api/persons/' + route.params.slug + '/' + id).then((response) => {
            tableData.value.splice(index,1);
            getCelebs();
        });
    }
    const syncItem = async () => {
        axios.put('/api/updateceleb',{ data: {id:singleData.value.id_celeb} }).then((response) => {
            showItem();
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
        route,
        loader,
        error,
        syncItem,
        showItem,
        removeItem,
        updatePageSize,
        updateCurrentPage,
        updateSort,
        updateSpin,
        updateSearchQuery,
        getCelebs,
    }
});
