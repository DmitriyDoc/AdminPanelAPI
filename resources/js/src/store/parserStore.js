import { defineStore } from "pinia";
import axios from 'axios'
import { ref } from "vue";
import {ElMessage} from "element-plus";

export const useParserStore = defineStore('parserStore',() => {
    const loader = ref(true);
    const error = ref(null);

    const addCelebById = async (idCeleb) => {
        axios.put('/updateceleb',{ data: {
                id:idCeleb,
                type:'Celebs',
                imageType:'event',
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
        axios.post('/parser',{ data: params
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
    const test = async () =>{
        try {
            axios.get('/translate/celebs').then((response) => {

            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        }
    }
    return {
        loader,
        error,
        test,
        tags,
        addCelebById,
        parserStart,
    }
});
