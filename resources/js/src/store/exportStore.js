import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { ref,reactive} from "vue";
import {ElMessage} from "element-plus";


export const useExportStore = defineStore('exportStore',() => {
    const movieStore = useExportStore();
    const route = useRoute();


    return {

    }
});
