import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                        'resources/js/supabase-admin.js',
                'resources/js/auth.js',
                'resources/js/parent-auth.js',
                'resources/js/staff-auth.js',
                'resources/js/staff-pickup.js',
                'resources/js/staff-parents.js',
                'resources/js/parent-dashboard.js',
                'resources/js/staff-students.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
