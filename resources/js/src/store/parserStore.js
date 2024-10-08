import { defineStore } from "pinia";
import axios from 'axios'
import { ref } from "vue";
import {ElMessage} from "element-plus";

export const useParserStore = defineStore('parserStore',() => {
    const state = ref({

    });
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

    return {
        loader,
        error,
        addCelebById,
        parserStart
    }
});
