const path = require('path');
const webpack = require('webpack');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const VueLoaderPlugin = require('vue-loader/lib/plugin');

module.exports = (env, argv) => {
    const devMode = argv.mode !== 'production';
    return {
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
            //'theme-sketchy': './src/themes/sketchy.scss', // build error
            'theme-slate': './src/themes/slate.scss',
            'theme-solar': './src/themes/solar.scss',
            'theme-spacelab': './src/themes/spacelab.scss',
            'theme-superhero': './src/themes/superhero.scss',
            'theme-united': './src/themes/united.scss',
            'theme-yeti': './src/themes/yeti.scss',
            'vendor': './src/vendor.js',
            'app': './src/index.js',
        },
        output: {
            path: path.resolve(__dirname, '../web/dist'),
            filename: devMode ? '[name].js' : '[name].[chunkhash].js'
        },
        module: {
            rules: [{
                test: /\.(css|scss)$/,
                use: [
                    //devMode ? 'style-loader' : MiniCssExtractPlugin.loader,
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'sass-loader'
                ]
            }, {
                // for font awesome fonts
                test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
                use: [{
                    loader: 'file-loader',
                    options: { name: '../fonts/[name].[ext]' }
                }]
            }, {
                // Swagger client AMD define fails, https://github.com/swagger-api/swagger-codegen/issues/3466
                test: /brvneucore-js-client\/.*\.js$/,
                use: 'imports-loader?define=>false'
            }, {
                test: /\.vue$/,
                loader: 'vue-loader'
            }, {
                test: /\.js$/,
                exclude: /(node_modules|brvneucore-js-client)/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['env']
                    }
                }
            }]
        },
        plugins: [
            new HtmlWebpackPlugin({
                template: 'src/index.html',
                filename: '../index.html',
                excludeChunks: [
                    'theme-basic',
                    'theme-cerulean',
                    'theme-cosmo',
                    'theme-cyborg',
                    //'theme-darkly',
                    'theme-flatly',
                    'theme-journal',
                    'theme-litera',
                    'theme-lumen',
                    'theme-lux',
                    'theme-materia',
                    'theme-minty',
                    'theme-pulse',
                    'theme-sandstone',
                    'theme-simplex',
                    'theme-sketchy',
                    'theme-slate',
                    'theme-solar',
                    'theme-spacelab',
                    'theme-superhero',
                    'theme-united',
                    'theme-yeti',
                ],
            }),
            new CleanWebpackPlugin(['dist', 'fonts'], {
                root: path.resolve(__dirname, '../web')
            }),
            new webpack.DefinePlugin({
                'process.env.NODE_ENV': JSON.stringify(devMode ? 'development' : 'production')
            }),
            new MiniCssExtractPlugin({
                filename: devMode ? '[name].css' : '[name].[hash].css',
                //chunkFilename: devMode ? '[id].css' : '[id].[chunkhash].css',
            }),
            new VueLoaderPlugin(),
        ],
        optimization: {
            runtimeChunk: 'single',
            minimizer: [
                new UglifyJsPlugin({
                    sourceMap: true
                }),
                new OptimizeCSSAssetsPlugin({
                    cssProcessorOptions: { safe: true },
                })
            ]
        },
        devtool: devMode ? 'inline-source-map' : 'source-map',
        performance: { hints: false },
    }
};
