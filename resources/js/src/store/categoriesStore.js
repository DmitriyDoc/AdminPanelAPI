import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";

export const useCategoriesStore = defineStore('categoriesStore',() => {
    const optionsCats = ref([]);
    const getCategories = async () =>{
        try {
            axios.get('/api/categories/').then((response) => {
                optionsCats.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }

    const getCategoriesFranchise = async () =>{
        try {
            axios.get('/api/categories/select_franchise').then((response) => {
                optionsCats.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const getCategoriesCollection = async () =>{
        try {
            axios.get('/api/categories/select_collection').then((response) => {
                optionsCats.value = response.data;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategories = async (data) => {
        try {
            axios.post('/api/categories',data).then((response) => {
                });
        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategoryFranchise = async (data) => {
        try {
            axios.post('/api/categories/franchise',data).then((response) => {
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const setCategoryCollection = async (data) => {
        try {
            axios.post('/api/categories/collection',data).then((response) => {
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
