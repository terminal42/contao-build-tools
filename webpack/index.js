const fs = require('fs');

const getPublicDir = () => {
    const composerConfig = require(`${ process.cwd() }/composer.json`);

    return composerConfig['extra']['public-dir'] || (fs.existsSync(`${ process.cwd() }/web`) ? 'web' : 'public');
}

class AddHtaccessPlugin {
    apply(compiler) {
        compiler.hooks.done.tap('AddHtaccessPlugin', () => {
            fs.copyFileSync(
                fs.existsSync(`${ process.cwd() }/${ this.layoutDir }/.htaccess`) ? `${ process.cwd() }/${ this.layoutDir }/.htaccess` : `${ __dirname }/.htaccess`,
                `${ compiler.outputPath }/.htaccess`,
            );
        });
    }
}

const buildEncore = (layoutDir = 'layout', detectEntries = true) => {
    const Encore = require('@symfony/webpack-encore');
    const ImageMinimizerPlugin = require('image-minimizer-webpack-plugin');

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

        .addPlugin(new ImageMinimizerPlugin({
            minimizer: {
                implementation: ImageMinimizerPlugin.imageminMinify,
                options: {
                    plugins: [
                        'imagemin-gifsicle',
                        'imagemin-mozjpeg',
                        'imagemin-pngquant',
                        'imagemin-svgo',
                    ],
                },
            },
        }))

        .addPlugin(new AddHtaccessPlugin())

        .configureDevServerOptions((options) => Object.assign({}, options, {
            static: false,
            hot: true,
            liveReload: true,
            allowedHosts: 'all',
            watchFiles: ['config/*', 'contao/**/*', 'src/**/*', 'templates/**/*', 'translations/**/*'],
            client: {
                overlay: false
            },
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
                    'autoprefixer': ["defaults"],
                }
            }
        });
    }

    return Encore;
}

module.exports = {
    Encore: buildEncore,
}
