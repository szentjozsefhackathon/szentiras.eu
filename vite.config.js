import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        hmr: {
            host: 'localhost'
        },
        host: '0.0.0.0',
    },
    plugins: [
        laravel({
            input: ['resources/assets/less/app.less', 
                'resources/assets/js/app.js', 
                'resources/assets/js/pages/home.js',
                'resources/assets/js/pages/translation.js'],
            refresh: true,
        }),
    ],
});