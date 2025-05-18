import { defineConfig } from 'vite';
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import i18n from 'laravel-vue-i18n/vite';
import path from 'path'

export default defineConfig({
    // server: {
    //     watch: {
    //         usePolling: true,
    //     },
    //     host: "0.0.0.0",
    //     port: 5173,
    // },
    server: {
        watch: {
            usePolling: true,
        },
        port: 5173,
        cors: {
            origin: "http://spectrum.local",
            methods: ["GET", "POST"],
            allowedHeaders: ["Content-Type", "Authorization"],
            preflightContinue: true
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
        i18n(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname,'resources/js/src')
        }
    }
});
