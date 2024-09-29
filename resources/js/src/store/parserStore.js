import { defineStore } from "pinia";
import axios from 'axios'
import { ref } from "vue";
import {ElMessage} from "element-plus";

export const useParserStore = defineStore('parserStore',() => {
    const state = ref({

    });
    const parserReport = ref({});
    const toggleButton = ref(true);
    const loader = ref(true);
    const error = ref(null);

    const addCelebById = async (idCeleb) => {
        axios.put('/api/updateceleb',{ data: {
                id:idCeleb,
                type:'Celebs'
            }}).then((response) => {
            if (response.status === 200) {
                ElMessage({
                    type: 'success',
                    message: 'Celeb add completed',
                })
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Celeb add is not finished',
                });
            }
        });
    };
    const parserStart = async (params) => {
        axios.post('/api/parser',{ data: params
            }).then((response) => {
                if (response.status === 200) {
                    ElMessage({
                        type: 'success',
                        message: 'Parser is complete',
                    })
                } else {
                    ElMessage({
                        type: 'error',
                        message: 'Parser is not finished',
                    });
                }
            });
    };
    const getReportParser = async () => {
        await axios.get('/api/updatemovie/tracking?sesKey=parseMovieReport'
            ).then((response) => {
                if (Object.keys(response.data).length){
                    parserReport.value = response.data;
                }
                if (parserReport.value.parseMovieReport?.stop){
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
        parserReport,
        toggleButton,
        loader,
        error,
        addCelebById,
        parserStart,
        getReportParser,
    }
});
