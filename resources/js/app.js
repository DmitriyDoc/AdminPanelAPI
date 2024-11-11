import { createApp } from 'vue'
import App from './src/App.vue'
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

axios.defaults.baseURL = `/api`;
axios.defaults.withCredentials = true;

createApp(App).use(createPinia()).use(Router).use(Toast, {
    pauseOnFocusLoss: false,
    hideProgressBar: true,
    timeout: 10000,
}).use(ElementPlus).mount("#app")
