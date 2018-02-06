const path = require('path');
const webpack = require('webpack');

module.exports = {
  entry: './index.ts',
  output: { path: path.resolve(__dirname, '../web'), filename: 'index.js' },
  module: {
    rules: [
      {
        test: /\.vue$/,
        loader: 'vue-loader',
        options: {
          loaders: {
            'scss': 'vue-style-loader!css-loader!sass-loader',
            'sass': 'vue-style-loader!css-loader!sass-loader?indentedSyntax',
          }
        }
      },
      {
        test: /\.tsx?$/,
        loader: 'ts-loader',
        exclude: /node_modules/,
        options: {
          appendTsSuffixTo: [/\.vue$/],
        }
      },
      {
        test: /\.(png|jpg|gif|svg)$/,
        loader: 'file-loader',
        options: { name: '[name].[ext]?[hash]' }
      }
    ]
  },
  resolve: {
    extensions: ['.ts', '.js', '.vue', '.json'],
    alias: { 'vue$': 'vue/dist/vue.esm.js' }
  },
  devServer: { historyApiFallback: true, noInfo: true },
  performance: { hints: false },
  devtool: '#eval-source-map'
}

// This is currently broken, UglifyJsPlugin throws a strange error
if (process.env.NODE_ENV === 'production') {
  module.exports.devtool =
    '#source-map'
  // http://vue-loader.vuejs.org/en/workflow/production.html
  module.exports.plugins = (module.exports.plugins || []).concat([
    new webpack.DefinePlugin({ 'process.env': { NODE_ENV: '"production"' } }),
    new webpack.optimize.UglifyJsPlugin(
      { ecma: 8, compress: { warnings: false } }),
    new webpack.LoaderOptionsPlugin({ minimize: true })
  ])
}