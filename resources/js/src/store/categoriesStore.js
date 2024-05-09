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

    const getCategoriesShow = async () =>{
        try {
            axios.get('/api/categories/show').then((response) => {
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
    const setCategory = async (data) => {
        try {
            axios.post('/api/category',data).then((response) => {
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    return {
        optionsCats,
        getCategories,
        setCategories,
        setCategory,
        getCategoriesShow,
    }
});
