const { defineConfig } = require('@vue/cli-service')
const path = require('path');
const CompressionPlugin = require("compression-webpack-plugin");
const LicenseWebpackPlugin = require('license-webpack-plugin').LicenseWebpackPlugin;

const themes = {
    'theme-basic': './src/themes/basic.scss',
    'theme-cerulean': './src/themes/cerulean.scss',
    'theme-cosmo': './src/themes/cosmo.scss',
    'theme-cyborg': './src/themes/cyborg.scss',
    'theme-darkly': './src/themes/darkly.scss',
    'theme-flatly': './src/themes/flatly.scss',
    'theme-journal': './src/themes/journal.scss',
    'theme-litera': './src/themes/litera.scss',
    'theme-lumen': './src/themes/lumen.scss',
    'theme-lux': './src/themes/lux.scss',
    'theme-materia': './src/themes/materia.scss',
    'theme-minty': './src/themes/minty.scss',
    'theme-pulse': './src/themes/pulse.scss',
    'theme-sandstone': './src/themes/sandstone.scss',
    'theme-simplex': './src/themes/simplex.scss',
    'theme-sketchy': './src/themes/sketchy.scss',
    'theme-slate': './src/themes/slate.scss',
    'theme-solar': './src/themes/solar.scss',
    'theme-spacelab': './src/themes/spacelab.scss',
    'theme-superhero': './src/themes/superhero.scss',
    'theme-united': './src/themes/united.scss',
    'theme-yeti': './src/themes/yeti.scss',
};

module.exports = defineConfig(() => {
    const production = process.env.NODE_ENV === 'production';
    return {
        outputDir: path.resolve(__dirname, '../web/dist'),
        publicPath: production ? 'dist/' : '',
        css: {
            extract: true, // necessary for themes in dev mode
        },
        configureWebpack: config => {
            config.resolve = {
                fallback: { 'querystring': require.resolve('querystring-es3') },
            };
            config.entry = themes;
            config.entry.main = './src/main.js';
            if (production) {
                config.plugins.push(new CompressionPlugin({
                    test: /\.(js|css)$/,
                    threshold: 1,
                    compressionOptions: { level: 6 },
                }));
                config.plugins.push(new LicenseWebpackPlugin({
                    perChunkOutput: false,
                }));
            }
        },
        chainWebpack: config => {
            config.module
                .rule('datatables')
                .test(/datatables\.net.*\.js$/)
                .use('imports-loader')
                .loader('imports-loader')
                .options({
                    additionalCode: 'var define = false;', // Disable AMD
                })
                .end()
            config.plugin('html').tap(args => {
                args[0].inject = false; // files are manually injected in index.html
                if (production) {
                    args[0].filename = path.resolve(__dirname, '../web/index.html');
                }
                return args;
            });
            if (production) {
                // noinspection NpmUsedModulesInstalled
                config.plugin("progress").use(require("webpack/lib/ProgressPlugin"))
            }
        },
    };
});
