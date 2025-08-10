import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";
import {ElMessage, ElMessageBox} from "element-plus";

export const useCategoriesStore = defineStore('categoriesStore',() => {
    const optionsCats = ref([]);
    const collectionLocale = ref([]);
    const franchiseLocale = ref([]);
    const getCategories = async () =>{
        try {
            axios.get('/categories/').then((response) => {
                optionsCats.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const getLocale = async () =>{
        try {
            axios.get('/categories/local').then((response) => {
                collectionLocale.value = response.data.collection;
                franchiseLocale.value = response.data.franchise;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const getCategoriesFranchise = async () =>{
        try {
            axios.get('/categories/select_franchise').then((response) => {
                optionsCats.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const getCategoriesCollection = async () =>{
        try {
            axios.get('/categories/select_collection').then((response) => {
                optionsCats.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategories = async (data) => {
        try {
            const response = await axios.post('/categories', data);
            if (response.data.success) {
                ElMessage({
                    type: 'success',
                    message: 'Collection selected',
                });
            } else {
                ElMessage({
                    type: 'warning',
                    message: 'Warning! This movie must has Russian or USSR collection',
                });
            }
        } catch (e) {
            console.log('error', e);
        }
    };
    const setCategoryFranchise = async (data) => {
        try {
            ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                axios.post('/categories/franchise',data).then((response) => {
                    if (!response.data.errors) {
                        ElMessage({
                            type: 'success',
                            message: 'Franchise added',
                        })
                    } else {
                        ElMessage({
                            type: 'error',
                            message: 'Error adding franchise',
                        })
                    }
                });
            }).catch(() => {
                ElMessage({
                    type: 'info',
                    message: 'Addition of a new franchise has been canceled',
                })
            })

        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategoryCollection = async (data) => {
        try {
            ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                axios.post('/categories/collection',data).then((response) => {
                    if (!response.data.errors) {
                        ElMessage({
                            type: 'success',
                            message: 'Collection added',
                        })
                    } else {
                        ElMessage({
                            type: 'error',
                            message: 'Error adding collection',
                        })
                    }
                });
            }).catch(() => {
                ElMessage({
                    type: 'info',
                    message: 'Addition of a new collection has been canceled',
                })
            })

        } catch (e) {
            console.log('error',e);
        }
    }
    return {
        optionsCats,
        collectionLocale,
        franchiseLocale,
        getLocale,
        getCategories,
        setCategories,
        setCategoryFranchise,
        setCategoryCollection,
        getCategoriesFranchise,
        getCategoriesCollection
    }
});
