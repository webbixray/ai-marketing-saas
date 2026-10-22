import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@components': path.resolve(__dirname, './resources/js/components'),
            '@hooks': path.resolve(__dirname, './resources/js/hooks'),
            '@pages': path.resolve(__dirname, './resources/js/pages'),
            '@stores': path.resolve(__dirname, './resources/js/stores'),
            '@services': path.resolve(__dirname, './resources/js/services'),
            '@utils': path.resolve(__dirname, './resources/js/utils'),
            '@types': path.resolve(__dirname, './resources/js/types'),
        },
    },
    server: {
        port: 5173,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
                secure: false,
            },
            '/sanctum': {
                target: 'http://localhost:8000',
                changeOrigin: true,
                secure: false,
            },
        },
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        sourcemap: true,
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom', 'react-router-dom'],
                    query: ['@tanstack/react-query'],
                    table: ['@tanstack/react-table'],
                    charts: ['chart.js', 'react-chartjs-2', 'recharts'],
                    ui: ['@headlessui/react', '@heroicons/react', 'lucide-react', 'framer-motion'],
                    forms: ['react-hook-form', 'zod'],
                    utils: ['axios', 'date-fns', 'clsx', 'tailwind-merge', 'zustand'],
                },
            },
        },
    },
});
