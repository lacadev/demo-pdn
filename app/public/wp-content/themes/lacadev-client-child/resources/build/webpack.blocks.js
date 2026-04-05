const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const fs = require('fs');

// Scan Child-specific blocks (nếu có sau này)
const childBlocksDir = path.resolve(__dirname, '../../block-gutenberg');
let childBlockConfigs = [];

if (fs.existsSync(childBlocksDir)) {
  const blocks = fs.readdirSync(childBlocksDir).filter(dir => {
    return fs.statSync(path.join(childBlocksDir, dir)).isDirectory() && fs.existsSync(path.join(childBlocksDir, dir, 'block.json'));
  });

  childBlockConfigs = blocks.map(block => {
    return {
      ...defaultConfig,
      entry: {
        index: path.join(childBlocksDir, block, 'index.js')
      },
      output: {
        ...defaultConfig.output,
        path: path.join(childBlocksDir, block, 'build'),
        filename: '[name].js'
      }
    };
  });
}

// Global Gutenberg bundle: Từ PARENT theme -> dist/gutenberg/ của Child
const parentBlocksDir = path.resolve(__dirname, '../../../lacadev-client/block-gutenberg');
const gutenbergLegacyConfig = {
  ...defaultConfig,
  entry: {
    index: path.join(parentBlocksDir, 'index.js'),
  },
  output: {
    ...defaultConfig.output,
    path: path.resolve(__dirname, '../../dist/gutenberg'),
    filename: '[name].js',
  },
  resolve: {
    ...defaultConfig.resolve,
    modules: [
      ...(defaultConfig.resolve?.modules || ['node_modules']),
      path.resolve(__dirname, '../../node_modules')
    ]
  }
};

module.exports = [...childBlockConfigs, gutenbergLegacyConfig];
