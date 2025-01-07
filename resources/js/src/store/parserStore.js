import { defineStore } from "pinia";
import axios from 'axios'
import { ref } from "vue";
import {ElMessage} from "element-plus";

export const useParserStore = defineStore('parserStore',() => {
    const locale = ref([]);
    const localeDatePicker = ref({});
    const filters = ref([]);
    const types = ref([]);
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
    const getLocale = async () => {
        try {
            axios.get('/parser/locale').then((response) => {
                locale.value = response.data.parser;
                filters.value = response.data.filter;
                types.value = response.data.types;
                localeDatePicker.value = response.data.datepicker;
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        }
    };
    const test = async () =>{
        try {
            axios.get('/transfer').then((response) => {

            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        }
    }
    return {
        locale,
        localeDatePicker,
        filters,
        types,
        loader,
        error,
        test,
        addCelebById,
        getLocale,
        parserStart,
    }
});
