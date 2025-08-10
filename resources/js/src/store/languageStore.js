import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";
import { loadLanguageAsync } from 'laravel-vue-i18n';
export const useLanguageStore = defineStore('languageStore',() => {
    const currentLang = ref('ru');
    const watcherLang = ref(null);
    const loader = ref(true);
    const error =  ref(null);

    const changeLang = async (lang) =>{
        try {
             await axios.get('/locale'+ '?lang=' + lang).then((response) => {
                 currentLang.value = response.data;
             });
        } catch (error) {
            error.value = error;
            console.log('error',error);
        } finally {
            loadLanguageAsync(currentLang.value);
            watcherLang.value = currentLang.value;
        }
    }

    return {
        watcherLang,
        currentLang,
        loader,
        error,
        changeLang,
    }
});
