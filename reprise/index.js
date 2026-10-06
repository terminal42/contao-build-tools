import fs from 'node:fs';
import path from 'node:path';
import Symfony from '@symfony/reprise/vite';
import basicSsl from '@vitejs/plugin-basic-ssl';
import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';
import postcssrc from 'postcss-load-config';
import fullReload from 'vite-plugin-full-reload';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

const getPublicDir = () => {
    const composerConfig = JSON.parse(fs.readFileSync(`${ process.cwd() }/composer.json`, 'utf8'));

    return composerConfig.extra?.['public-dir'] || (fs.existsSync(`${ process.cwd() }/web`) ? 'web' : 'public');
};

const buildReprise = async (assetsDir, detectEntries, {
    outputPath = `${ getPublicDir() }/build`,
    publicPath = '/build/',
    copy = [],
}) => {
    const input = {};
    const sourcePath = path.resolve(assetsDir);
    let postcss;

    try {
        await postcssrc({}, process.cwd());
    } catch (error) {
        if (!error.message.startsWith('No PostCSS Config found')) {
            throw error;
        }

        postcss = {
            plugins: [
                autoprefixer({ overrideBrowserslist: ['defaults'] }),
                cssnano({
                    preset: ['default', {
                        mergeLonghand: false,
                        discardComments: { removeAll: true },
                        reduceIdents: false,
                        minifySelectors: false,
                        discardDuplicates: true,
                        discardEmpty: true,
                        normalizeWhitespace: true,
                        calc: { precision: 5 },
                    }],
                }),
            ],
        };
    }

    if (detectEntries && fs.existsSync(sourcePath)) {
        fs.readdirSync(sourcePath, { withFileTypes: true }).filter(file => file.isFile() && /\.m?js$/.test(file.name) && !file.name.startsWith('_')).forEach((file) => {
            input[file.name.replace(/\.m?js$/, '')] = path.join(sourcePath, file.name);
        });
    }

    return {
        publicDir: false,
        css: {
            devSourcemap: true,
            preprocessorOptions: {
                scss: {
                    quietDeps: true,
                    silenceDeprecations: ['import'],
                },
            },
            postcss,
        },
        build: {
            outDir: outputPath,
            emptyOutDir: true,
            sourcemap: true,
            assetsInlineLimit: 0,
            rollupOptions: {
                input,
                output: {
                    entryFileNames: 'js/[name]-[hash].js',
                    chunkFileNames: 'js/[name]-[hash].js',
                    assetFileNames: (assetInfo) => {
                        const name = assetInfo.name || '';

                        // Fonts
                        if (/\.(woff2?|ttf|eot|otf)$/i.test(name)) {
                            return 'fonts/[name]-[hash][extname]';
                        }

                        // Images
                        if (/\.(png|jpe?g|gif|svg|webp|avif|ico)$/i.test(name)) {
                            return 'images/[name]-[hash][extname]';
                        }

                        // CSS
                        if (/\.css$/i.test(name)) {
                            return 'css/[name]-[hash][extname]';
                        }

                        return '[name]-[hash][extname]';
                    },
                },
            },
        },
        plugins: [
            Symfony({
                outputPath,
                publicPath,
                manifestKeyPrefix: '',
                copy: [
                    ...(fs.existsSync(`${ sourcePath }/images`) ? [{ from: `${ sourcePath }/images`, to: 'images' }] : []),
                    ...copy,
                ],
            }),
            basicSsl(),
            ViteImageOptimizer({
                exclude: /\/fa-.+\.svg$/, // Ignore font-awesome icons
            }),
            fullReload(['config/*', 'contao/**/*', 'src/**/*', 'templates/**/*', 'translations/**/*']),
            {
                name: 'contao-build-tools-htaccess',
                generateBundle() {
                    this.emitFile({
                        type: 'asset',
                        fileName: '.htaccess',
                        source: fs.readFileSync(fs.existsSync(`${ sourcePath }/.htaccess`) ? `${ sourcePath }/.htaccess` : new URL('.htaccess', import.meta.url)),
                    });
                },
            },
        ],
        server: {
            cors: true,
            hmr: { overlay: false },
        },
    };
};

export default (assetsDir = 'layout', detectEntries = true) => {
    const options = {};
    const callbacks = [];

    return {
        addEntry(name, file) {
            return this.configureVite(config => { config.build.rollupOptions.input[name] = path.resolve(file); });
        },
        enableSourceMaps(enabled = true) {
            return this.configureVite(config => { config.build.sourcemap = enabled; });
        },
        setOutputPath(outputPath) {
            options.outputPath = outputPath;
            return this;
        },
        setPublicPath(publicPath) {
            options.publicPath = publicPath;
            return this;
        },
        copyFiles(copyOptions) {
            options.copy ??= [];
            options.copy.push(copyOptions);
            return this;
        },
        addPlugin(plugin) {
            return this.configureVite(config => { config.plugins.push(plugin); });
        },
        configureDevServerOptions(callback) {
            return this.configureVite(config => callback(config.server));
        },
        configureVite(callback) {
            callbacks.push(callback);
            return this;
        },
        async getViteConfig() {
            const config = await buildReprise(assetsDir, detectEntries, options);

            for (const callback of callbacks) {
                await callback(config);
            }

            return config;
        },
    };
};
