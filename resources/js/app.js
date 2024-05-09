import {createPinia} from "pinia";
import {createApp} from 'vue'
import App from './src/App.vue'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import Router from "./src/router/router.js";


createApp(App).use(createPinia()).use(Router).use(ElementPlus).mount("#app")
