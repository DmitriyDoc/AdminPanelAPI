import { defineStore } from "pinia";
import axios from 'axios';
import { useRoute } from 'vue-router';
import { ref } from "vue";
import { ElMessage } from "element-plus";

export const useCollectionsStore = defineStore('collectionsStore', () => {
    const state = ref({
        searchQuery: '',
        spinParam: 'asc',
        sortBy: 'id',
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
    const collectionsList = ref([]);
    const franchises = ref([]);
    const title = ref('');
    const locale = ref([]);
    const route = useRoute();
    const collectionImagesData = ref({});

    const getDataCollections = async () => {
        try {
            loader.value = true;
            const response = await axios.get('/collections/' +
                route.params.slug + '/' +
                route.params.collName +
                '?page=' + state.value.page +
                '&limit=' + state.value.limit +
                '&orderBy=' + state.value.sortBy +
                '&spin=' + state.value.spinParam +
                '&search=' + state.value.searchQuery
            );
            locale.value = response.data.locale;
            collectionsData.value = response.data.data;
            franchises.value = response.data.franchise;
            title.value = response.data.title;
            totalCount.value = response.data.total;
            loader.value = false;
        } catch (e) {
            console.log('error', e);
        }
    };

    const getListCollections = async () => {
        try {
            loader.value = true;
            const response = await axios.get('/collections' +
                '?page=' + state.value.page +
                '&limit=' + state.value.limit +
                '&orderBy=' + state.value.sortBy +
                '&spin=' + state.value.spinParam +
                '&search=' + state.value.searchQuery
            );
            locale.value = response.data.locale;
            collectionsList.value = response.data.data;
            totalCount.value = response.data.total;
            loader.value = false;
        } catch (e) {
            console.log('error', e);
        }
    };

    const removeItemFromCollection = async (id, index) => {
        try {
            await axios.delete('/collections/del', {
                data: { id }
            });
            collectionsList.value.splice(index, 1);
            ElMessage({
                type: 'success',
                message: 'Collection deleted',
            });
        } catch (e) {
            console.log('error', e);
            ElMessage({
                type: 'error',
                message: 'Error deleting collection',
            });
        }
    };

    const updateCollection = async (collection) => {
        try {
            const response = await axios.post('/collections/update', collection);
            if (response.data.success) {
                // ElMessage({
                //     type: 'success',
                //     message: 'Collection updated successfully',
                // });
            } else {
                ElMessage({
                    type: 'warning',
                    message: response.data.message || 'Error updating collection',
                });
            }
        } catch (e) {
            console.log('error', e);
            ElMessage({
                type: 'error',
                message: 'Server error',
            });
        }
    };

    const updateCollectionImages = async (formData) => {
        try {
            const response = await axios.post('/collections/update-images', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (response.data.success) {
                ElMessage({
                    type: 'success',
                    message: 'Image updated successfully',
                });
            } else {
                ElMessage({
                    type: 'warning',
                    message: response.data.message || 'Error updating image',
                });
            }
        } catch (e) {
            console.log('error', e);
            ElMessage({
                type: 'error',
                message: 'Server error',
            });
        }
    };

    const removeImage = async (file, collectionId) => {
        try {
            await axios.delete('/collections/images', {
                data: { collectionId, file: file.name }
            });
            ElMessage.success('Image deleted');
        } catch (error) {
            console.error('Error deleting image:', error);
            ElMessage.error('Error deleting image');
        }
    };

    const getCollectionImages = async () => {
        try {
            const response = await axios.get('/collections/images');
            collectionImagesData.value = response.data;
        } catch (e) {
            console.log('error', e);
        }
    };

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
    };

    return {
        optionsCats,
        collectionsData,
        collectionsList,
        franchises,
        locale,
        title,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        route,
        loader,
        collectionImagesData,
        removeItemFromCollection,
        getDataCollections,
        getListCollections,
        updateCollection,
        updateCollectionImages,
        removeImage,
        getCollectionImages,
        updateSearchQuery,
        updateSpin,
        updateSort,
        updateCurrentPage,
        updatePageSize,
    };
});
