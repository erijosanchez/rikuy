import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    server: {
        // Vite corre en el HOST; el navegador (también en el host) lo alcanza en
        // localhost:5173. cors:true permite que la página servida por el contenedor
        // (localhost:8000) cargue los assets del dev server (otro origen).
        host: 'localhost',
        port: 5173,
        strictPort: true,
        cors: true,
        hmr: { host: 'localhost' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
