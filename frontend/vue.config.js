const { defineConfig } = require('@vue/cli-service')
const path = require('path');
const CompressionPlugin = require('compression-webpack-plugin');
// css-minimizer-webpack-plugin does not need to be required in package.json
// noinspection NpmUsedModulesInstalled
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const LicenseWebpackPlugin = require('license-webpack-plugin').LicenseWebpackPlugin;

module.exports = defineConfig(() => {
    const production = process.env.NODE_ENV === 'production';
    return {
        outputDir: path.resolve(__dirname, '../web/dist'),
        publicPath: production ? '/dist/' : '/',
        css: {
            loaderOptions: {
                scss: {
                    sassOptions: {
                        // Bootstrap 5.3 still uses deprecated Sass features (e.g. @import).
                        // Suppress the resulting deprecation warnings from node_modules.
                        quietDeps: true,
                    },
                },
            },
        },
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
                // cssnano's svgo plugin cannot parse the percent-encoded SVG data URIs that
                // Bootstrap 5.3 ships (e.g. the form-switch and accordion icons): Dart Sass
                // leaves the '%' of percentage-based colours unencoded, so svgo fails with
                // SvgoParserError warnings. Disable svgo to keep the build clean.
                config.optimization.minimizer = config.optimization.minimizer.map(minimizer => {
                    if (minimizer instanceof CssMinimizerPlugin) {
                        return new CssMinimizerPlugin({
                            minimizerOptions: {
                                preset: ['default', {
                                    mergeLonghand: false,
                                    cssDeclarationSorter: false,
                                    svgo: false,
                                }],
                            },
                        });
                    }
                    return minimizer;
                });
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
