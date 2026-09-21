import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const ambiente = loadEnv(mode, process.cwd(), '');
    const porta = Number(ambiente.VITE_PORT || 5174);

    return {
        plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true })],
        server: {
            host: '0.0.0.0',
            port: porta,
            strictPort: true,
            hmr: { host: 'localhost', clientPort: porta },
            watch: { ignored: ['**/storage/framework/views/**'] },
        },
    };
});
