const { defineConfig } = require('@vue/cli-service')
const path = require('path');
const CompressionPlugin = require('compression-webpack-plugin');
const LicenseWebpackPlugin = require('license-webpack-plugin').LicenseWebpackPlugin;

module.exports = defineConfig(() => {
    const production = process.env.NODE_ENV === 'production';
    return {
        outputDir: path.resolve(__dirname, '../web/dist'),
        publicPath: production ? '/dist/' : '/',
        configureWebpack: config => {
            config.resolve = {
                fallback: { 'querystring': require.resolve('querystring-es3') },
            };
            config.entry = {
                main: './src/main.js',
            };
            if (production) {
                config.plugins.push(new CompressionPlugin({
                    test: /\.(js|css)$/,
                    threshold: 1,
                    compressionOptions: { level: 6 },
                }));
                config.plugins.push(new LicenseWebpackPlugin({ perChunkOutput: false }));
            }
        },
        chainWebpack: config => {
            config.module
                .rule('datatables')
                .test(/datatables\.net.*\.js$/)
                .use('imports-loader')
                .loader('imports-loader')
                .options({ additionalCode: 'var define = false;', }) // Disable AMD
                .end()
            config.plugin('html').tap(args => {
                args[0].inject = false; // Files are manually injected in index.html.
                if (production) {
                    args[0].filename = path.resolve(__dirname, '../web/index.html');
                }
                return args;
            });
            if (production) {
                config.plugin('copy').tap(args => {
                    // This favicon.ico is only used for dev mode to prevent 404 errors.
                    // For production, it's already in web/favicon.ico.
                    args[0].patterns[0].globOptions.ignore.push(path.resolve(__dirname, 'public/favicon.ico'));
                    return args;
                });
                config.plugin('progress').use(require('webpack/lib/ProgressPlugin'))
            }
        },
    };
});
