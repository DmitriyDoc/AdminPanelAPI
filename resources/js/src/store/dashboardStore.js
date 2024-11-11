import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";

export const useDashboardStore = defineStore('dashboardStore',() => {
    const countCard = ref([]);
    const loader = ref(true);
    const error =  ref(null);

    const getMoviesCount = async () =>{
        try {
             await axios.get('/dashboard').then((response) => {
                 countCard.value = response.data.data
             });
        } catch (error) {
            error.value = error;
            console.log('error',error);
        } finally {
            //loader.value = false;
            setTimeout(() => loader.value = false, 1000);
        }
    }

    return {
        countCard,
        loader,
        error,
        getMoviesCount,
    }
});
