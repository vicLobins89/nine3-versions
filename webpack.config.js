const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  module: {
    ...defaultConfig.module
  },
  plugins: [
    ...defaultConfig.plugins
  ]
};