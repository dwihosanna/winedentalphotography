import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/patients.js'],
            refresh: true,
        }),
    ],
    server: {
        fs: { allow: ['..', '../../packages'] },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
