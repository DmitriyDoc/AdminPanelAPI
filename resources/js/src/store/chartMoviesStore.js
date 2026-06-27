import { defineStore } from 'pinia';
import axios from 'axios';
import { useRoute } from 'vue-router';
import { ref, computed } from 'vue';
import { ElMessage } from 'element-plus';

export const useChartMoviesStore = defineStore('chartMoviesStore', () => {
    const state = ref({
        searchQuery: '',
        spinParam: 'asc',
        sortBy: 'position',
        page: 1,
        limit: 50,
    });

    const route = useRoute();
    const tableData = ref([]);
    const locale = ref({});
    const chartInfo = ref(null);
    const totalCount = ref(0);

    // 🔧 КЛЮЧЕВОЕ ИЗМЕНЕНИЕ: computed вместо ref
    // currentPage и pageSize — это "окна" в state, а не копии
    const currentPage = computed({
        get: () => state.value.page,
        set: (val) => { state.value.page = val; }
    });

    const pageSize = computed({
        get: () => state.value.limit,
        set: (val) => { state.value.limit = val; }
    });

    const valueSort = computed({
        get: () => state.value.sortBy,
        set: (val) => { state.value.sortBy = val; }
    });

    const loader = ref(true);
    const error = ref(null);

    const getChartMovies = () => {
        try {
            loader.value = true;
            const chartId = route.params.chart_id;

            // 🔧 Теперь state.value.limit всегда актуален
            const url = `/charts/${chartId}?page=${state.value.page}&limit=${state.value.limit}&orderBy=${state.value.sortBy}&spin=${state.value.spinParam}&search=${state.value.searchQuery}`;

            axios.get(url)
                .then((response) => {
                    locale.value = response.data.locale;
                    tableData.value = response.data.data;
                    totalCount.value = response.data.total;
                    chartInfo.value = response.data.chart_info;
                    loader.value = false;
                })
                .catch((e) => {
                    error.value = e;
                    console.log('error', e);
                    loader.value = false;
                });
        } catch (e) {
            error.value = e;
            console.log('error', e);
            loader.value = false;
        }
    }

    // 🔧 Синхронизация фильма с IMDB
    const syncMovie = (movieId, typeFilmString) => {
        axios.post('/updatemovie', {
            data: {
                id: movieId,
                type: typeFilmString,
                posterType: 'poster',
                typeSync: 'full_sync'
            }
        }).then((response) => {
            if (response.status === 200) {
                ElMessage({
                    type: 'success',
                    message: 'Synchronization with IMDB is queued',
                });
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Synchronization with IMDB is not finished and not queued.',
                });
            }
        }).catch((e) => {
            ElMessage({
                type: 'error',
                message: 'Error queueing synchronization',
            });
            console.log('sync error', e);
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
        // 🔧 ВАЖНО: При смене размера страницы сбрасываем на первую страницу
        state.value.page = 1;
    }

    return {
        tableData,
        chartInfo,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        locale,
        loader,
        error,
        syncMovie,
        updatePageSize,
        updateCurrentPage,
        updateSort,
        updateSpin,
        updateSearchQuery,
        getChartMovies,
    }
});
