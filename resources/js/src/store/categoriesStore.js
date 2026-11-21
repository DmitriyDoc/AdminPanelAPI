import { defineStore } from "pinia";
import axios from 'axios';
import { ref } from "vue";
import { ElMessage } from "element-plus";

export const useCategoriesStore = defineStore('categoriesStore', () => {
    const optionsCats = ref([]);
    const collectionLocale = ref([]);
    const franchiseLocale = ref([]);
    const collectionImages = ref({});

    const getCategories = async () => {
        try {
            const response = await axios.get('/categories/');
            optionsCats.value = response.data;
        } catch (e) { console.log('error', e); }
    };

    const getLocale = async () => {
        try {
            const response = await axios.get('/categories/local');
            collectionLocale.value = response.data.collection;
            franchiseLocale.value = response.data.franchise;
        } catch (e) { console.log('error', e); }
    };

    const getCategoriesList = async () => {
        const response = await axios.get('/categories/manage/list');
        return response.data;
    };

    // Category CRUD
    const createCategory = async (data) => {
        const response = await axios.post('/categories/manage/category', data);
        if (response.data.success) ElMessage.success('Created');
        return response.data;
    };
    const updateCategory = async (id, data) => {
        const response = await axios.patch(`/categories/manage/category/${id}`, data);
        if (response.data.success) ElMessage.success('Updated');
        return response.data;
    };
    const deleteCategory = async (id) => {
        const response = await axios.delete(`/categories/manage/category/${id}`);
        if (response.data.success) ElMessage.success('Deleted');
        return response.data;
    };

    // Collection CRUD
    const createCollection = async (data) => {
        const response = await axios.post('/categories/manage/collection', data);
        if (response.data.success) ElMessage.success('Created');
        return response.data;
    };
    const updateCollection = async (id, data) => {
        const response = await axios.patch(`/categories/manage/collection/${id}`, data);
        if (response.data.success) ElMessage.success('Updated');
        return response.data;
    };
    const deleteCollection = async (id) => {
        const response = await axios.delete(`/categories/manage/collection/${id}`);
        if (response.data.success) ElMessage.success('Deleted');
        return response.data;
    };

    // Franchise CRUD
    const createFranchise = async (data) => {
        const response = await axios.post('/categories/manage/franchise', data);
        if (response.data.success) ElMessage.success('Created');
        return response.data;
    };
    const updateFranchise = async (id, data) => {
        const response = await axios.patch(`/categories/manage/franchise/${id}`, data);
        if (response.data.success) ElMessage.success('Updated');
        return response.data;
    };
    const deleteFranchise = async (id) => {
        const response = await axios.delete(`/categories/manage/franchise/${id}`);
        if (response.data.success) ElMessage.success('Deleted');
        return response.data;
    };

    // Move collection between sections
    const moveCollection = async (data) => {
        const response = await axios.post('/categories/manage/move-collection', data);
        if (response.data.success) ElMessage.success('Collection moved');
        return response.data;
    };

    // Assign/reassign franchise to single collection
    const assignFranchiseToCollection = async (data) => {
        const response = await axios.post('/categories/manage/assign-franchise-to-collection', data);
        if (response.data.success) ElMessage.success('Franchise assigned');
        return response.data;
    };

    // Sync all collections of a franchise (transfer)
    const syncFranchiseCollections = async (data) => {
        const response = await axios.post('/categories/manage/sync-franchise-collections', data);
        if (response.data.success) ElMessage.success('Synced');
        return response.data;
    };
// Collection images
    const getCollectionImages = async () => {
        try {
            const response = await axios.get('/categories/manage/collection-images');
            collectionImages.value = response.data;
            return response.data;
        } catch (e) { console.log('error', e); }
    };

    const updateCollectionImage = async (formData) => {
        try {
            const response = await axios.post('/categories/manage/collection-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (response.data.success) {
                ElMessage.success('Image updated');
                await getCollectionImages();
            }
            return response.data;
        } catch (e) {
            console.log('error', e);
            ElMessage.error('Error updating image');
        }
    };

    const deleteCollectionImage = async (collectionId, file) => {
        try {
            const response = await axios.delete('/categories/manage/collection-image', {
                data: { collectionId, file }
            });
            if (response.data.success) {
                ElMessage.success('Image deleted');
                await getCollectionImages();
            }
            return response.data;
        } catch (e) {
            console.log('error', e);
            ElMessage.error('Error deleting image');
        }
    };
    // Legacy (сохраняем для обратной совместимости)
    const getCategoriesFranchise = async () => {
        try {
            const response = await axios.get('/categories/select_franchise');
            optionsCats.value = response.data;
        } catch (e) { console.log('error', e); }
    };
    const getCategoriesCollection = async () => {
        try {
            const response = await axios.get('/categories/select_collection');
            optionsCats.value = response.data;
        } catch (e) { console.log('error', e); }
    };
    const setCategories = async (data) => {
        try {
            const response = await axios.post('/categories', data);
            if (response.data.success) ElMessage.success('Collection selected');
            else ElMessage.warning('Warning! Movie must have Russian/USSR collection');
        } catch (e) { console.log('error', e); }
    };
    const setCategoryFranchise = async (data) => {
        try {
            const response = await axios.post('/categories/franchise', data);
            if (!response.data.errors) ElMessage.success('Franchise added');
            else ElMessage.error('Error adding franchise');
        } catch (e) { console.log('error', e); }
    };
    const setCategoryCollection = async (data) => {
        try {
            const response = await axios.post('/categories/collection', data);
            if (!response.data.errors) ElMessage.success('Collection added');
            else ElMessage.error('Error adding collection');
        } catch (e) { console.log('error', e); }
    };

    return {
        optionsCats, collectionLocale, franchiseLocale,
        getLocale, getCategories,
        setCategories, setCategoryFranchise, setCategoryCollection,
        getCategoriesFranchise, getCategoriesCollection,
        getCategoriesList,
        createCategory, updateCategory, deleteCategory,
        createCollection, updateCollection, deleteCollection,
        createFranchise, updateFranchise, deleteFranchise,
        moveCollection, assignFranchiseToCollection, syncFranchiseCollections,collectionImages,
        getCollectionImages,
        updateCollectionImage,
        deleteCollectionImage,
    };
});
