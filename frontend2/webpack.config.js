const path = require('path');

module.exports = {
    entry: {
        'vendor': './src/vendor.js',
        'app': './src/index.js'
    },
    output: {
        path: path.resolve(__dirname, '../web'),
        filename: '[name].js'
    },
    module: {
        rules: [
            {
                test: /\.(css|scss)$/,
                use: [
                    'style-loader', 'css-loader', 'sass-loader'
                ]
            }, {
                // for open-iconic fonts
                test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: 'fonts/[name].[ext]'
                        }
                    }
                ]
            }, {
                // AMD define module paths fails in Webpack
                // https://github.com/swagger-api/swagger-codegen/issues/3466
                test: /brvneucore-js-client\/.*\.js$/,
                use: 'imports-loader?define=>false'
            }
        ]
    }
};
