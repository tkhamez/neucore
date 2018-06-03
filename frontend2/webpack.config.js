const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const webpack = require('webpack');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

module.exports = (env, argv) => {
    const devMode = argv.mode !== 'production';
    return {
        entry: {
            'vendor': './src/vendor.js',
            'app': './src/index.js'
        },
        output: {
            path: path.resolve(__dirname, '../web/dist'),
            filename: devMode ? '[name].js' : '[name].[chunkhash].js'
        },
        module: {
            rules: [{
                test: /\.(css|scss)$/,
                use: [
                    devMode ? 'style-loader' : MiniCssExtractPlugin.loader,
                    'css-loader',
                    'sass-loader'
                ]
            }, {
                // for open-iconic fonts
                test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
                use: [{
                    loader: 'file-loader',
                    options: { name: '../fonts/[name].[ext]' }
                }]
            }, {
                // Swagger client AMD define fails, https://github.com/swagger-api/swagger-codegen/issues/3466
                test: /brvneucore-js-client\/.*\.js$/,
                use: 'imports-loader?define=>false'
            }]
        },
        plugins: [
            new HtmlWebpackPlugin({
                template: 'src/index.html',
                filename: '../index.html'
            }),
            new CleanWebpackPlugin(['dist'], {
                root: path.resolve(__dirname, '../web')
            }),
            new webpack.DefinePlugin({
                'process.env.NODE_ENV': JSON.stringify(devMode ? 'development' : 'production')
            }),
            new MiniCssExtractPlugin({
                filename: "[name].[chunkhash].css",
                //chunkFilename: "[name].[id].[chunkhash].css"
            })
        ],
        optimization: {
            runtimeChunk: true,
            splitChunks: { chunks: 'all' }
        },
        devtool: devMode ? 'inline-source-map' : 'source-map',
        performance: { hints: false }
    }
};
