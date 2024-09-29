import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";

export const useDashboardStore = defineStore('dashboardStore',() => {
    const countCard = ref([]);
    const loader = ref(true);
    const error =  ref(null);
    const percentage = ref(0);

    const getMoviesCount = async () =>{
        try {
             await axios.get('/api/dashboard').then((response) => {
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
    const getCurrentPercentage = async () => {
          await axios.get('/api/updatemovie/tracking?sesKey=dashboardPercentageBar'
        ).then((response) => {
            percentage.value = response.data.dashboardPercentageBar ?? 0;
            if (percentage.value < 100){
                setTimeout(function () {
                    getCurrentPercentage();
                }, 1000);
            }
        });
    }
    return {
        countCard,
        percentage,
        loader,
        error,
        getMoviesCount,
        getCurrentPercentage,
    }
});
