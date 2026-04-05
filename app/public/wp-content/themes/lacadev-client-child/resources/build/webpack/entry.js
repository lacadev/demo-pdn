/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');
const path = require('path');

// Trỏ trực tiếp source scripts về parent theme
const parentSrc = path.resolve(__dirname, '../../../../lacadev-client/resources/scripts');

module.exports = {
  'theme': path.join(parentSrc, 'theme/index.js'),
  'admin': path.join(parentSrc, 'admin/index.js'),
  'login': path.join(parentSrc, 'login/index.js'),
  'editor': path.join(parentSrc, 'editor/index.js'),
  // Entry thêm dành riêng cho child theme (để override SCSS)
  'child': utils.srcStylesPath('child.scss'),
};
