import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";
import {ElMessage} from "element-plus";

export const useCategoriesStore = defineStore('categoriesStore',() => {
    const optionsCats = ref([]);
    const getCategories = async () =>{
        try {
            axios.get('/categories/').then((response) => {
                optionsCats.value = response.data;
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
            axios.post('/categories',data).then((response) => {
                if (response.data.success){
                    ElMessage({
                        type: 'success',
                        message: 'Collection selected',
                    })
                } else {
                    ElMessage({
                        type: 'warning',
                        message: 'Warning! This movie must has Russian or USSR collection',
                    })
                }
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategoryFranchise = async (data) => {
        try {
            axios.post('/categories/franchise',data).then((response) => {
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategoryCollection = async (data) => {
        try {
            axios.post('/categories/collection',data).then((response) => {
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    return {
        optionsCats,
        getCategories,
        setCategories,
        setCategoryFranchise,
        setCategoryCollection,
        getCategoriesFranchise,
        getCategoriesCollection
    }
});
