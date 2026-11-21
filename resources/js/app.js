import { createApp } from 'vue'
import App from './src/App.vue'
import { i18nVue } from 'laravel-vue-i18n'
import './app.css';
import 'vue-select/dist/vue-select.css';
import './src/assets/css/vue-select-override.css';
import { createPinia } from "pinia";
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import Router from "./src/router/router.js";
import axios from 'axios';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

// ==========================================
// ГЛОБАЛЬНАЯ НАСТРОЙКА AXIOS (КРИТИЧЕСКИ ВАЖНО)
// ==========================================
axios.defaults.baseURL = `/api`;
axios.defaults.withCredentials = true; // Разрешает отправку и получение куки (сессия, XSRF-TOKEN)
axios.defaults.withXSRFToken = true;   // Автоматически читает куку 'XSRF-TOKEN' и подставляет её в заголовок 'X-XSRF-TOKEN'

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        if (error.response && (error.response.status === 401 || error.response.status === 419) && !originalRequest._retry) {
            originalRequest._retry = true;
            try {
                await axios.get('/sanctum/csrf-cookie');
                return axios(originalRequest);
            } catch (refreshError) {
                window.location.href = '/';
                return Promise.reject(refreshError);
            }
        }
        return Promise.reject(error);
    }
);

createApp(App).use(i18nVue, {
    resolve: async lang => {
        const langs = import.meta.glob('../../lang_vue/*.json');
        return await langs[`../../lang_vue/${lang}.json`]();
    }

}).use(createPinia()).use(Router).use(Toast, {
    pauseOnFocusLoss: false,
    hideProgressBar: true,
    timeout: 10000,
}).use(ElementPlus).mount("#app")
