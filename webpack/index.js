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

const buildEncore = (assetsDir = 'layout', detectEntries = true) => {
    const Encore = require('@symfony/webpack-encore');
    const ImageMinimizerPlugin = require('image-minimizer-webpack-plugin');

    if (!Encore.isRuntimeEnvironmentConfigured()) {
        Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
    }

    // Allow to build multiple Encore configurations
    Encore.reset();

    Encore
        .setOutputPath(`${ getPublicDir() }/${ assetsDir }/`)
        .setPublicPath(`/${ assetsDir }`)
        .setManifestKeyPrefix('')
        .cleanupOutputBeforeBuild()
        .disableSingleRuntimeChunk()

        .configureImageRule({
            type: 'asset',
        })

        .configureBabel((config) => {
            config.plugins.push('@babel/plugin-transform-class-properties');
            config.plugins.push('@babel/plugin-transform-private-methods');
        })

        // enables @babel/preset-env polyfills
        .configureBabelPresetEnv((config) => {
            config.useBuiltIns = 'usage';
            config.corejs = 3;
        })

        .enableSassLoader()
        .enablePostCssLoader()
        .enableSourceMaps()
        .enableVersioning(Encore.isProduction())

        .addPlugin(new ImageMinimizerPlugin({
            exclude: /\/fa-.+\.svg$/, // Ignore font-awesome icons
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

        .configureDevServerOptions(options => {
            options.static = false;
            options.hot = true;
            options.liveReload = true;
            options.allowedHosts = 'all';
            options.static = { watch: false }
            options.watchFiles = { paths: ['config/*', 'contao/**/*', 'src/**/*', 'templates/**/*', 'translations/**/*'] };
            options.server = { type: "https" };
            options.client = { overlay: false };
        })
    ;

    // Automatically detect JavaScript files in the layout folder and add Encore entries
    if (detectEntries && fs.existsSync(`${ process.cwd() }/${ assetsDir }`)) {
        fs.readdirSync(`${ process.cwd() }/${ assetsDir }/`).filter(f => f.endsWith('.js')).forEach((file) => {
            // Skip files with _ prefix.
            if (file.substring(0, 1) === '_') {
                return;
            }

            Encore.addEntry(
                file.substring(0, file.length - 3),
                `./${ assetsDir }/${ file }`
            );
        });
    }

    // Automatically add all images to make them available in Twig templates
    if (fs.existsSync(`${ process.cwd() }/${ assetsDir }/images`)) {
        Encore.copyFiles({
            from: `${ process.cwd() }/${ assetsDir }/images`,
            to: 'images/[path][name].[hash:8].[ext]',
            // pattern: /\.(gif|png|jpe?g|svg|webp)$/
        });
    }

    // Automatically configure PostCSS if there is no config in the project
    if (!fs.existsSync(`${ process.cwd() }/postcss.config.js`)) {
        Encore.enablePostCssLoader((options) => {
            options.postcssOptions = {
                plugins: {
                    autoprefixer: ['defaults'],
                    cssnano: {
                        preset: [
                            'default',
                            {
                                mergeLonghand: false,
                                discardComments: {
                                    removeAll: true,
                                },
                                reduceIdents: false,
                                minifySelectors: false,
                                discardDuplicates: true,
                                discardEmpty: true,
                                normalizeWhitespace: true,
                                calc: {
                                    precision: 5,
                                },
                            },
                        ],
                    },
                }
            }
        });
    }

    return Encore;
}

module.exports = buildEncore;
