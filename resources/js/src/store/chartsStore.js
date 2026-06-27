import { defineStore } from 'pinia';
import axios from 'axios';
import { useRoute } from 'vue-router';
import { ref, computed } from 'vue';
import { ElMessage } from 'element-plus';

export const useChartsStore = defineStore('chartsStore', () => {
    // Инициализация состояния
    const state = ref({
        searchQuery: '',
        spinParam: 'desc',
        sortBy: 'updated_at',
        page: 1,
        limit: 50,
    });

    const route = useRoute();

    // 🔧 КЛЮЧЕВОЕ ИЗМЕНЕНИЕ: computed вместо ref
    // Теперь currentPage и pageSize — это "окна" в state, а не копии
    // v-model в компоненте будет напрямую писать в state.value.page / state.value.limit
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

    // Реактивные данные
    const tableData = ref([]);
    const locale = ref({});
    const totalCount = ref(0);
    const loader = ref(true);
    const error = ref(null);

    // Состояние для парсера
    const parserLoading = ref(false);

    // Метод получения данных
    const getCharts = () => {
        try {
            loader.value = true;
            // 🔧 Теперь state.value.limit всегда актуален (синхронизирован через computed)
            const url = `/charts?page=${state.value.page}&limit=${state.value.limit}&orderBy=${state.value.sortBy}&spin=${state.value.spinParam}&search=${state.value.searchQuery}`;

            axios.get(url)
                .then((response) => {
                    locale.value = response.data.locale;
                    tableData.value = response.data.data;
                    totalCount.value = response.data.total;
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
    };

    // Метод удаления
    const removeItem = (chartId, index) => {
        axios.delete('/charts', {
            data: { 'chart_id': chartId }
        })
            .then((response) => {
                if (response.data.success) {
                    tableData.value.splice(index, 1);
                    totalCount.value--;
                    ElMessage({
                        type: 'success',
                        message: 'Delete completed',
                    });
                } else {
                    ElMessage({
                        type: 'error',
                        message: response.data.message || 'Delete error',
                    });
                }
            })
            .catch(() => {
                ElMessage({
                    type: 'error',
                    message: 'Delete error',
                });
            });
    };

    // --- ЛОГИКА ПАРСЕРА ---

    const syncChart = (chartId) => {
        parserLoading.value = true;
        axios.post('/parser/charts', {
            data: {
                action: 'update_one',
                chart_id: chartId
            }
        }).then((response) => {
            if (response.data.status === 'success') {
                ElMessage({
                    type: 'success',
                    message: response.data.message || 'Parsing queued',
                });
                setTimeout(() => getCharts(), 2000);
            } else {
                ElMessage({
                    type: 'warning',
                    message: response.data.message || 'Error queueing task',
                });
            }
        }).catch((e) => {
            ElMessage({
                type: 'error',
                message: 'Server error during parsing request',
            });
        }).finally(() => {
            parserLoading.value = false;
        });
    };

    const syncAllCharts = () => {
        parserLoading.value = true;
        axios.post('/parser/charts', {
            data: {
                action: 'update_all'
            }
        }).then((response) => {
            if (response.data.status === 'success') {
                ElMessage({
                    type: 'success',
                    message: response.data.message || 'All charts parsing queued',
                });
            }
        }).catch((e) => {
            ElMessage({
                type: 'error',
                message: 'Server error',
            });
        }).finally(() => {
            parserLoading.value = false;
        });
    };

    state.value.searchQuery = '';

    // Обновляющие методы
    const updateSearchQuery = (q) => {
        state.value.searchQuery = q;
    };

    const updateSpin = (param) => {
        state.value.spinParam = param;
    };

    const updateSort = (param) => {
        state.value.sortBy = param;
    };

    const updateCurrentPage = (param) => {
        state.value.page = param;
    };

    const updatePageSize = (param) => {
        state.value.limit = param;
        // 🔧 ВАЖНО: При смене размера страницы сбрасываем на первую страницу
        // Иначе можно оказаться на несуществующей странице
        // (было 10 страниц по 50, стало 2 по 300, а currentPage = 7)
        state.value.page = 1;
    };

    return {
        tableData,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        locale,
        loader,
        error,
        parserLoading,
        removeItem,
        syncChart,
        syncAllCharts,
        updatePageSize,
        updateCurrentPage,
        updateSort,
        updateSpin,
        updateSearchQuery,
        getCharts,
    };
});
