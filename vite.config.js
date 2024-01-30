import { defineConfig } from 'vite';
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    server: {
        hmr : {
            host: 'admin.local'
        }
    },
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js',],
            refresh: true,
        }),
        AutoImport({
            resolvers: [ElementPlusResolver()],
        }),
        Components({
            resolvers: [ElementPlusResolver()],
        }),
    ],
   resolve: {
       alias: {
           '@': path.resolve(__dirname,'resources/js/src')
       }
   }
});
