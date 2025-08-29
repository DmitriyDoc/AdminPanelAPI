import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref }  from "vue";
import {ElMessage} from "element-plus";


export const useSectionStore = defineStore('sectionStore',() => {

    const state = ref({
        searchQuery: '',
        spinParam: 'desc',
        sortBy: 'updated_at',
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
    const collections = ref([]);
    const title = ref('');
    const locale = ref([]);
    const route = useRoute();
    const sectionImagesData = ref({});

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
            loader.value = true;
            axios.get('/sections/'
                + route.params.slug
                + '?page=' + state.value.page
                + '&limit=' + state.value.limit
                + '&orderBy=' + state.value.sortBy
                + '&spin=' + state.value.spinParam
                + '&search=' + state.value.searchQuery
            ).then((response) => {
                locale.value = response.data.locale;
                sectionsData.value = response.data.data;
                collections.value = response.data.collections;
                title.value = response.data.title;
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
    const removeImage = async (file, sectionId, field) => {
        try {
            await axios.delete('/section/images', {
                data: { sectionId, field, file: file.name }
            });
            ElMessage.success('Изображение удалено');
        } catch (error) {
            console.error('Error deleting image:', error);
            ElMessage.error('Ошибка при удалении изображения');
        }
    };
    const updateSection = async (data) => {
        try {
            const response = await axios.post('/section/update', data);
            if (response.data) {
                ElMessage({
                    type: 'success',
                    message: 'Section updated',
                });
            } else {
                ElMessage({
                    type: 'warning',
                    message: 'Error update section',
                });
            }
        } catch (e) {
            console.log('error', e);
        }
    }
    const updateSectionImages = async (formData) => {
        try {
            const response = await axios.post('/section/update-images', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (response.data.success) {
                ElMessage({
                    type: 'success',
                    message: 'Images and posters updated successfully',
                });
            } else {
                ElMessage({
                    type: 'warning',
                    message: response.data.message || 'Error updating images',
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
    const getSectionImages = async () => {
        try {
            const response = await axios.get('/section/images');
            sectionImagesData.value = response.data;
        } catch (e) {
            console.log('error', e);
        }
    };
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
        sectionImagesData,
        optionsCats,
        sections,
        collections,
        title,
        sectionsData,
        totalCount,
        currentPage,
        pageSize,
        valueSort,
        locale,
        route,
        loader,
        removeImage,
        updateSectionImages,
        getSectionImages,
        removeItemFromSection,
        updateSection,
        getSections,
        getDataSections,
        updateSearchQuery,
        updateSpin,
        updateSort,
        updateCurrentPage,
        updatePageSize,
    }
});
