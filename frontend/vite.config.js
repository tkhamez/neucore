import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import {fileURLToPath} from 'node:url';

import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import dotenv from 'dotenv';
import {expand as dotenvExpand} from 'dotenv-expand';
import rollupPluginLicense from 'rollup-plugin-license';

const root = path.dirname(fileURLToPath(import.meta.url));
const webDir = path.resolve(root, '../web');
const outDir = path.join(webDir, 'dist');

/**
 * Load the .env files (with variable expansion, e.g. VITE_BACKEND_HOST=$BACKEND_HOST)
 * into process.env, mimicking the dotenv/dotenv-expand behaviour of Vue CLI. Vite
 * exposes them as import.meta.env.VITE_* (its default env prefix).
 */
function loadEnvFiles(mode) {
    for (const file of ['.env', '.env.local', `.env.${mode}`, `.env.${mode}.local`]) {
        const filePath = path.join(root, file);
        if (!fs.existsSync(filePath)) {
            continue;
        }
        const result = dotenv.config({path: filePath, quiet: true});
        if (result.parsed) {
            dotenvExpand(result);
        }
    }
}

export default defineConfig(({mode}) => {
    loadEnvFiles(mode);

    const base = mode === 'production' ? '/dist/' : '/';

    return {
        base,
        resolve: {
            alias: [
                {
                    // @suadelabs/vue3-multiselect only declares its CJS/UMD entry in
                    // "main"; its ESM build is used instead. The UMD build ships bundled
                    // core-js polyfills whose initialisation calls new Function(), which
                    // is blocked by the app's CSP.
                    find: /^@suadelabs\/vue3-multiselect$/,
                    replacement: '@suadelabs/vue3-multiselect/dist/vue3-multiselect.esm.js',
                },
            ],
        },
        plugins: [
            vue(),
            // Write the licences of all bundled third-party packages to
            // web/dist/licenses.txt (replaces webpack's LicenseWebpackPlugin).
            rollupPluginLicense({
                thirdParty: {
                    output: {
                        file: path.join(outDir, 'licenses.txt'),
                    },
                },
            }),
            {
                // Inject the plain (non-module) public scripts (browser-check.js,
                // setup.js, custom.js) right before the app module script. This keeps
                // the historical HTML layout and avoids Vite trying to bundle them.
                name: 'neucore:html-scripts',
                transformIndexHtml: {
                    order: 'post',
                    handler: html => {
                        const scripts = ['browser-check.js', 'setup.js', 'custom.js']
                            .map(file => `<script type="text/javascript" src="${base}${file}"></script>`)
                            .join('\n        ');
                        let result = html.replace(
                            '<script type="module"',
                            `${scripts}\n        <script type="module"`,
                        );
                        // In production, the favicon is served from web/favicon.ico (not
                        // from the dist directory); undo Vite's base-rewriting of the link.
                        result = result.replace(`href="${base}favicon.ico"`, 'href="/favicon.ico"');
                        return result;
                    },
                },
            },
            {
                // Keep the historical artefact layout: index.html lives in web/ (not
                // web/dist/), and the dev-only favicon from public/ is not part of the
                // production output.
                name: 'neucore:html-output',
                apply: 'build',
                closeBundle() {
                    fs.renameSync(path.join(outDir, 'index.html'), path.join(webDir, 'index.html'));
                    fs.rmSync(path.join(outDir, 'favicon.ico'), {force: true});
                },
            },
            {
                // Gzip the built JS/CSS assets so that nginx's "gzip_static" directive
                // can serve them (replaces webpack's CompressionPlugin).
                // custom.js is excluded on purpose: it is a hook for administrators to
                // add their own code in an installed Neucore.
                name: 'neucore:compress',
                apply: 'build',
                closeBundle() {
                    const walk = dir => {
                        for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
                            const filePath = path.join(dir, entry.name);
                            if (entry.isDirectory()) {
                                walk(filePath);
                            }
                            else if (/\.(js|css)$/.test(entry.name) && entry.name !== 'custom.js') {
                                const compressed = zlib.gzipSync(fs.readFileSync(filePath), {level: 6});
                                fs.writeFileSync(`${filePath}.gz`, compressed);
                            }
                        }
                    };
                    walk(outDir);
                },
            },
            {
                // The home page Markdown is DB content rendered at runtime (v-html), so its
                // asset URLs cannot be base-rewritten by the build: it uses the production
                // URL /dist/img/... In dev, public files are served from the root, so map
                // /dist/* to them, keeping dev and prod asset URLs identical.
                name: 'neucore:dev-dist-alias',
                apply: 'serve',
                configureServer(server) {
                    server.middlewares.use((req, res, next) => {
                        if (req.url?.startsWith('/dist/')) {
                            req.url = req.url.slice('/dist'.length);
                        }
                        next();
                    });
                },
            },
        ],
        server: {
            host: true,
            port: 5173,
            strictPort: true,
            // The ESI path files (Member Data -> ESI page) are static files in
            // web/, served by the backend (nginx) in production. In dev the page
            // runs on :5173, so the app fetches them same-origin and the dev
            // server proxies the requests to the backend container. The target
            // must be the docker network address of the http container, not a
            // localhost URL (different container).
            proxy: {
                '/esi-paths-http-get.json': {
                    target: 'http://neucore_http:80',
                    changeOrigin: true,
                },
                '/esi-paths-http-post.json': {
                    target: 'http://neucore_http:80',
                    changeOrigin: true,
                },
            },
        },
        build: {
            outDir,
            emptyOutDir: true,
            // The CSP of the app does not allow inline scripts; all supported browsers
            // implement modulepreload, so the polyfill is not needed.
            modulePreloadPolyfill: false,
        },
        css: {
            preprocessorOptions: {
                scss: {
                    // Bootstrap 5.3 still uses deprecated Sass features (e.g. @import).
                    // Suppress the resulting deprecation warnings from node_modules.
                    quietDeps: true,
                },
            },
        },
    };
});
