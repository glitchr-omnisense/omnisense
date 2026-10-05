const Encore = require('@symfony/webpack-encore');
const Webpack = require('webpack');
const WebpackBar = require('webpackbar');
const MediaQueryPlugin = require('@glitchr/media-query-plugin');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'development');
}

Encore
    .addPlugin(new WebpackBar())

    // window.jQuery / window.$ are assigned explicitly in the entries (see
    // Chapaland's webpack.config.js for why 'window.jQuery' is not provided).
    .addPlugin(new Webpack.ProvidePlugin({ $: 'jquery', jQuery: 'jquery' }))

    .setOutputPath('./public/assets/')
    .setPublicPath('/assets')
    .setManifestKeyPrefix('.')

    // base-bundle reads app-async / app-defer / layout1-async as the tags
    // "app" and "layout1" (Base\Cache\Warmer\WebpackCacheWarmer).
    .addEntry('app-async', './assets/app-async.js')
    .addEntry('app-defer', './assets/app-defer.js')
    .addEntry('layout1-async', './assets/layout1-async.js')
    .addStyleEntry('email-layout1', './assets/styles/email.scss')

    // Brand art and icons, served as they are: /assets/images/brand/..., /assets/ico/...
    .copyFiles({ from: './assets/images', to: 'images/[path][name].[ext]' })
    .copyFiles({ from: './assets/ico', to: 'ico/[path][name].[ext]' })

    .splitEntryChunks()

    // enables the Symfony UX Stimulus bridge (used in assets/stimulus_bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // Root-absolute url()s (/assets/images/...) are runtime paths served by
    // copyFiles() above, not modules to resolve.
    .configureCssLoader((options) => {
        options.url = { filter: (url) => !url.startsWith('/') };
    })
    .enableSassLoader((options) => {
        options.sassOptions = { quietDeps: true };
    })
    .enablePostCssLoader()

    .addLoader({
        test: /\.scss$/,
        use: [MediaQueryPlugin.loader, 'postcss-loader', 'sass-loader']
    })

    // Split each entry's media queries into per-breakpoint files that
    // base-bundle loads with the matching media attribute (config/packages/
    // base.yaml, base.twig.breakpoints).
    .addPlugin(new MediaQueryPlugin({
        include: ['app-defer', 'app-async', 'layout1-async'],
        queries: {
            'all and (min-width: 1281px)': 'desktop',
            'all and (min-width: 1025px) and (max-width: 1280px)': 'laptop',
            'all and (min-width: 471px) and (max-width: 1024px)': 'tablet',
            'all and (min-width: 471px) and (max-width: 1024px) and (orientation: landscape)': 'tablet-landscape',
            'all and (max-width: 470px)': 'mobile',
            'all and (max-width: 470px) and (orientation: landscape)': 'mobile-landscape'
        }
    }))

    .addExternals({ jquery: 'jQuery' })
;

module.exports = Encore.getWebpackConfig();
module.exports.ignoreWarnings = [{ module: /node_modules/ }];

// File events do not reliably cross the macOS -> Docker bind mount: poll the
// sources only (Chapaland measured a 35% CPU watcher when it polled everything).
module.exports.watchOptions = {
    poll: 300,
    ignored: ['**/.git/**', '**/node_modules/**', '**/vendor/**', '**/var/**', '**/public/**']
};
