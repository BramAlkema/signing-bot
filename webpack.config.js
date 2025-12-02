const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = webpackConfig

// Additional entry points
webpackConfig.entry = {
    main: path.join(__dirname, 'src', 'main.js'),
    fileactions: path.join(__dirname, 'src', 'fileActions.js'),
    admin: path.join(__dirname, 'src', 'admin.js'),
}
