import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";

export const useProgressBarStore = defineStore('progressBarStore',() => {

    const percentage = ref(0);
    const statusBar = ref('');
    const percentageSync = ref(0);
    const parserReport = ref({});
    const toggleButton = ref(true);

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
    const getSyncCurrentPercentage = async (key) => {
        statusBar.value = '';
        await axios.get('/api/updateceleb/tracking?sesKey='+key
        ).then((response) => {
            percentageSync.value = response.data[key] ?? 0;
            if (percentageSync.value < 100){
                setTimeout(function () {
                    getSyncCurrentPercentage(key);
                }, 1000);
            }
            if (percentageSync.value === 100){
                setTimeout(() => statusBar.value = 'success', 1000);
            }
        });
    }
    const getReportParser = async () => {
        await axios.get('/api/updatemovie/tracking?sesKey=report'
        ).then((response) => {
            if (Object.keys(response.data).length){
                parserReport.value = response.data;
            }
            if (parserReport.value.report?.stop){
                toggleButton.value = false;
            }
            if (toggleButton.value){
                setTimeout(function () {
                    getReportParser();
                }, 1000);
            }
        });
    }
    return {
        percentage,
        statusBar,
        percentageSync,
        parserReport,
        getCurrentPercentage,
        getSyncCurrentPercentage,
        getReportParser
    }
});
