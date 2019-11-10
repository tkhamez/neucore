const path = require('path');
const webpack = require('webpack');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const TerserPlugin = require('terser-webpack-plugin');
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const VueLoaderPlugin = require('vue-loader/lib/plugin');
const LicenseWebpackPlugin = require('license-webpack-plugin').LicenseWebpackPlugin;
const GoogleFontsPlugin = require("@beyonk/google-fonts-webpack-plugin");
const CreateFileWebpack = require('create-file-webpack');

module.exports = (env, argv) => {
    const devMode = argv.mode !== 'production';
    const config = {
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
            'main': './src/main.js',
        },
        output: {
            path: path.resolve(__dirname, '../web/dist'),
            filename: devMode ? '[name].[hash].js' : '[name].[chunkhash].js'
        },
        module: {
            rules: [{
                test: /\.(css|scss)$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'sass-loader',
                ]
            }, {
                // for font awesome fonts
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
                test: /node_modules\/markdown-it-attrs\/.*\.js$/,
                use: 'babel-loader'
            }, {
                test: /datatables\.net.*\.js$/,
                loader: 'imports-loader?define=>false'
            }]
        },
        plugins: [
            new HtmlWebpackPlugin({
                template: 'src/index.html',
                filename: '../index.html',
                inject: false,
            }),
            new CleanWebpackPlugin({
                cleanOnceBeforeBuildPatterns: ['**/*', '../fonts/*'],
                dangerouslyAllowCleanPatternsOutsideProject: true,
                dry: false,
            }),
            new webpack.DefinePlugin({
                'process.env.NODE_ENV': JSON.stringify(devMode ? 'development' : 'production')
            }),
            new MiniCssExtractPlugin({
                filename: devMode ? '[name].[hash].css' : '[name].[chunkhash].css',
            }),
            new VueLoaderPlugin(),
            new GoogleFontsPlugin({
                fonts: [
                    { family: "Source Sans Pro", variants: ["300", "400", "700", "400italic"] }, // cosmo, lumen
                    { family: "Roboto", variants: ["300", "400", "500", "700"] }, // cyborg, materia, sandstone
                    { family: "Lato", variants: ["300", "400", "700", "400italic"] }, // darkly, flatly, superhero
                    { family: "News Cycle", variants: ["400", "700"] }, // journal
                    { family: "Nunito Sans", variants: ["400", "600"] }, // lux
                    { family: "Montserrat" }, // minty
                    { // simplex, spacelab, yeti
                        family: "Open Sans",
                        variants: ["300", "400", "700", "300italic", "400italic", "700italic"]
                    },
                    //{ family: "Neucha|Cabin Sketch" }, // sketchy
                    { family: "Source Sans Pro" }, // solar
                    { family: "Ubuntu", variants: ["400", "700"] }, // united
                ]
            }),
        ],
        optimization: {
            runtimeChunk: 'single',
            splitChunks: {
                chunks: 'all',
            },
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
    };
    if (! devMode) {
        config.plugins.push(new LicenseWebpackPlugin());
        config.plugins.push(new CreateFileWebpack({
            path: '../web/dist',
            fileName: 'fonts.license.txt',
            content: 'https://fonts.google.com/attribution'
        }));
    }
    return config;
};
