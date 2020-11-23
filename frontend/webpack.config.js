const path = require('path');
const webpack = require('webpack');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const CompressionPlugin = require("compression-webpack-plugin");
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const TerserPlugin = require('terser-webpack-plugin');
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const VueLoaderPlugin = require('vue-loader/lib/plugin');
const LicenseWebpackPlugin = require('license-webpack-plugin').LicenseWebpackPlugin;

module.exports = (env, argv) => {
    const devMode = argv.mode !== 'production';
    const config = {
        resolve: {
            fallback: { 'querystring': require.resolve('querystring-es3') }
        },
        entry: {
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
            'main': './src/main.js',
            'api': './src/swagger-ui.js',
        },
        output: {
            path: path.resolve(__dirname, '../web/dist'),
            filename: '[name].[contenthash].js'
        },
        target: ['web', 'es5'],
        module: {
            rules: [{
                test: /\.(css|scss)$/,
                use: [
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: { publicPath: '' },
                    },
                    'css-loader',
                    'sass-loader',
                ]
            }, {
                test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
                use: [{
                    loader: 'file-loader',
                    options: { name: '../fonts/[name].[ext]' }
                }]
            }, {
                test: /\.vue$/,
                loader: 'vue-loader'
            }, {
                test: /\.js$/,
                exclude: /(node_modules|neucore-js-client)/,
                loader: 'babel-loader'
            }, {
                test: /node_modules\/(markdown-it-attrs|punycode)\/.*\.js$/,
                use: 'babel-loader'
            }, {
                test: /datatables\.net.*\.js$/,
                use: [{
                    loader: 'imports-loader',
                    options: {
                        additionalCode: 'var define = false;', // Disable AMD
                    },
                }]
            }]
        },
        plugins: [
            new HtmlWebpackPlugin({
                template: 'src/index.html',
                filename: '../index.html',
                inject: false,
            }),
            new HtmlWebpackPlugin({
                template: 'src/api.html',
                filename: '../api.html',
                inject: false,
            }),
            new webpack.DefinePlugin({
                'process.env.NODE_ENV': JSON.stringify(devMode ? 'development' : 'production')
            }),
            new MiniCssExtractPlugin({
                filename: '[name].[contenthash].css'
            }),
            new VueLoaderPlugin(),
            new CleanWebpackPlugin({
                cleanOnceBeforeBuildPatterns: ['**/*', '../fonts/*'],
                dangerouslyAllowCleanPatternsOutsideProject: true,
                dry: false,
            }),
        ],
        optimization: {
            minimizer: [
                new TerserPlugin(),
                new OptimizeCSSAssetsPlugin({
                    cssProcessorOptions: { safe: true },
                })
            ]
        },
        devtool: devMode ? 'inline-source-map' : 'source-map',
        performance: {
            hints: devMode ? false : 'warning'
        },
        watchOptions: {
            ignored: /node_modules|neucore-js-client/
        }
    };
    if (! devMode) {
        config.plugins.push(new CompressionPlugin({
            test: /\.(js|css)$/,
            threshold: 1,
            compressionOptions: { level: 6 },
        }));
        config.plugins.push(new LicenseWebpackPlugin({
            // TODO This fixes "ERROR in Conflict: Multiple assets emit different content to the same
            // filename mini-css-extract-plugin.licenses.txt", but produces a lot of duplicates.
            outputFilename: '[name].[fullhash].licenses.txt',
        }));
    }
    return config;
};
