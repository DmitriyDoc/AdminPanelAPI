import { defineStore } from "pinia";
import axios from 'axios';
import { ElMessage } from "element-plus";
import { ref } from "vue";

export const useImportStore = defineStore('importStore', () => {
    // Точечный импорт
    const singleImportId = ref('');
    const singleImportType = ref('movie');
    const isLoadingSingle = ref(false);

    // Массовый импорт
    const bulkEntityType = ref('movie');
    const bulkCategory = ref('popular');
    const bulkPage = ref(1);
    const listItems = ref([]);
    const isLoadingList = ref(false);
    const selectedItems = ref([]);
    const availableLists = ref({ movie: [], person: [] });
    const totalPages = ref(1);
    const totalResults = ref(0);
    const submitSingleImport = async () => {
        if (!singleImportId.value) {
            ElMessage.warning('Введите ID (IMDb или TMDB)');
            return;
        }

        isLoadingSingle.value = true;
        try {
            const response = await axios.post('/import/one', {
                id: singleImportId.value,
                type: singleImportType.value
            });
            ElMessage.success(response.data.message);
            singleImportId.value = '';
        } catch (error) {
            const msg = error.response?.data?.message || 'Ошибка при импорте';
            ElMessage.error(msg);
        } finally {
            isLoadingSingle.value = false;
        }
    };

    const fetchLists = async () => {
        try {
            const res = await axios.get('/import/lists');
            availableLists.value = res.data;
        } catch (e) {
            console.error("Failed to fetch lists", e);
        }
    };

    const fetchListItems = async () => {
        isLoadingList.value = true;
        selectedItems.value = []; // Сброс выбранных при новой загрузке
        try {
            const res = await axios.get(`/import/list/${bulkEntityType.value}`, {
                params: {
                    category: bulkCategory.value,
                    page: bulkPage.value
                }
            });
            listItems.value = res.data.items || [];
            totalPages.value = res.data.total_pages || 1;
            totalResults.value = res.data.total_results || 0;
        } catch (error) {
            ElMessage.error('Ошибка загрузки списка');
        } finally {
            isLoadingList.value = false;
        }
    };

    const submitBulkImport = async () => {
        if (selectedItems.value.length === 0) {
            ElMessage.warning('Выберите элементы для импорта');
            return;
        }

        isLoadingList.value = true;
        try {
            const res = await axios.post('/import/many', {
                items: selectedItems.value
            });
            ElMessage.success(res.data.message);
            selectedItems.value = []; // Очистить выбор после успеха
            fetchListItems(); // Обновить список, чтобы показать обновленные статусы
        } catch (error) {
            ElMessage.error('Ошибка массового импорта');
        } finally {
            isLoadingList.value = false;
        }
    };
    const changePage = (newPage) => {
        bulkPage.value = newPage;
        fetchListItems();
    };
    return {
        singleImportId,
        singleImportType,
        isLoadingSingle,
        bulkEntityType,
        bulkCategory,
        bulkPage,
        listItems,
        isLoadingList,
        selectedItems,
        availableLists,
        totalPages,
        totalResults,
        changePage,
        fetchLists,
        fetchListItems,
        submitBulkImport,
        submitSingleImport
    };
});
