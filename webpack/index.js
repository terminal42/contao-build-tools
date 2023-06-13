const fs = require('fs');

const getPublicDir = () => {
    const composerConfig = require(`${ process.cwd() }/composer.json`);

    return composerConfig['extra']['public-dir'] || (fs.existsSync(`${ process.cwd() }/web`) ? 'web' : 'public');
}

const buildEncore = (layoutDir = 'layout', detectEntries = true) => {
    const Encore = require('@symfony/webpack-encore');

    Encore
        .setOutputPath(`${ getPublicDir() }/${ layoutDir }/`)
        .setPublicPath(`/${ layoutDir }`)
        .setManifestKeyPrefix('')
        .cleanupOutputBeforeBuild()
        .disableSingleRuntimeChunk()

        .enableSassLoader()
        .enablePostCssLoader()
        .enableSourceMaps()
        .enableVersioning(Encore.isProduction())

        .addLoader({
            test: /\.(gif|png|jpe?g|svg)$/i,
            use: ['image-webpack-loader']
        })

        .configureDevServerOptions(() => ({
            allowedHosts: 'all',
            watchFiles: ['config/*', 'contao/**/*', 'src/**/*', 'templates/**/*', 'translations/**/*'],
            headers: {
                "Access-Control-Allow-Origin": "*",
            }
        }))
    ;

    // Automatically detect JavaScript files in the layout folder and add Encore entries
    if (detectEntries && fs.existsSync(`${ process.cwd() }/${ layoutDir }`)) {
        fs.readdirSync(`${ process.cwd() }/${ layoutDir }/`).filter(f => f.endsWith('.js')).forEach((file) => {
            Encore.addEntry(
                file.substring(0, file.length - 3),
                `./${ layoutDir }/${ file }`
            );
        });
    }

    // Automatically configure PostCSS if there is no config in the project
    if (!fs.existsSync(`${ process.cwd() }/postcss.config.js`)) {
        Encore.enablePostCssLoader((options) => {
            options.postcssOptions = {
                plugins: {
                    'autoprefixer': {},
                }
            }
        });
    }

    return Encore;
}

module.exports = {
    Encore: buildEncore,
}
