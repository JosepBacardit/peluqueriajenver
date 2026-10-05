import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/critical.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // The Docker service maps the container's 5173 to host port 5175
        // (see docker-compose.yml), so the dev-server URL written to
        // public/hot must say 5175 for the browser to reach it.
        origin: 'http://localhost:5175',
        // laravel-vite-plugin defaults the CORS allow-origin to the value
        // above, which would block the module scripts because the page
        // itself is served from :8082, not :5175. Allow the app's real
        // origin explicitly.
        cors: { origin: ['http://localhost:8082'] },
        watch: {
            // N3 (review `agenda-service-filter`, coordinator): broadened
            // from just "storage/framework/views" to every directory the
            // dev server never needs to react to — vendor/node_modules are
            // on their own named volumes already (see docker-compose.yml),
            // but the watcher still polled them (and storage/, .git/,
            // public/build/) every 300ms otherwise. Complements
            // app.css's "source(none)" (the first CSS compile's own
            // slowdown, a separate one-off scan) by cutting the ongoing
            // per-poll cost too.
            ignored: ['**/vendor/**', '**/node_modules/**', '**/storage/**', '**/.git/**', '**/public/build/**'],
            // Native filesystem events don't reach the container from the
            // Windows bind mount, so edits went unnoticed until polling was
            // added here.
            usePolling: true,
            interval: 300,
        },
    },
});
