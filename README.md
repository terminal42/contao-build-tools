# terminal42 Contao Build Tools

This is an experimental repository to ease configuration of Contao bundles and websites.

**DO NOT USE IN PRODUCTION**

## Summary

This repo contains some highly opinionated configurations for our extensions and websites.
The CQ and CS tools currently assume that your bundle or application is set up according to
Symfony Best Practice for [applications][SFBP] or [bundles][SBPB], meaning there is an `src/`
directory where all your application or bundle code lives in, but none of the configuration.


## Code Quality and Code Style

This package automatically configures the root project for code quality and code style tools.
Whenever you run `composer install` or `composer update` on the project, it will also update
the build tools automatically. The following tools are currently available and can be executed
through the `composer run` command:

### Code Style Fixer

The `cs-fixer` script will fix the coding style in the `src/` directory according to the 
latest Contao coding standards. Create an `ecs.php` script in your project
to extend the default configuration.

You can extend the default configuration by adding a `ecs.php` file to your project root.

### Rector

The `rector` script will automatically upgrade the code to match the latest Contao standards.

You can extend the default configuration by adding a `rector.php` file to your project root.

### PHPStan

The `phpstan` script will check your code with PHPStan.

You can extend the default configuration by adding a `phpstan.neon` file to your project root.

### Stylelint

The `stylelint` script will check your CSS formatting with [Stylelint](https://stylelint.io).

You can extend the default configuration by adding a `.stylelintrc` file to your project root.


### Ideas

Ideas for additional tools that could be integrated:
 - maglnet/composer-require-checker
 - https://github.com/VincentLanglet/Twig-CS-Fixer


## Continuous Integration

To make sure your code is always up-to-date, you might want
to run all build tools at once but only verify and not fix files.
Run `composer run build-tools` to do this.

### Example GitHub Action

```yaml
# /.github/workflows/ci.yml
name: CI

on:
    push: ~
    pull_request: ~

permissions: read-all

jobs:
    ci:
        uses: 'terminal42/contao-build-tools/.github/workflows/build-tools.yml@main'
```



## Symfony Webpack Encore

The `webpack/` package provides the shared Symfony Webpack Encore defaults. Install
it in your application's `package.json`:

```json
{
    "devDependencies": {
        "@terminal42/contao-build-tools": "file:vendor/terminal42/contao-build-tools/webpack"
    },
    "scripts": {
        "dev": "encore dev",
        "watch": "encore dev --watch",
        "dev-server": "encore dev-server",
        "build": "encore production"
    }
}
```

Run `npm install`, then create `webpack.config.js`:

```js
const Encore = require('@terminal42/contao-build-tools');

module.exports = Encore().getWebpackConfig();
```

The helper returns the Encore instance, so you can customize it before calling
`getWebpackConfig()`. Pass a different assets directory as the first argument, or
`false` as the second to disable automatic entries and call `addEntry()` on the
returned instance yourself. For example, to define entries explicitly and customize
both Encore options and the generated Webpack settings:

```js
const Encore = require('@terminal42/contao-build-tools');

const config = Encore('layout', false)
    .addEntry('app', './layout/app.js')
    .enableSourceMaps(false)
    .getWebpackConfig();

config.performance = { hints: false };

module.exports = config;
```

By default, it detects top-level `layout/*.js` entries (excluding `_`-prefixed
files), outputs into the Composer `extra.public-dir` (falling back to `web` if
present, otherwise `public`), and uses `/layout` as the public path. It cleans
the output before building, disables the separate runtime chunk, enables source
maps, and versions production assets.

It enables Sass and PostCSS, with default Autoprefixer/cssnano settings unless the
application has a `postcss.config.js`. Babel transforms JavaScript for the configured
browser targets, including class properties and private methods. Its
`@babel/preset-env` configuration uses `core-js` 3 to inject polyfills based on
usage and browser targets.

Images in `layout/images/` are copied with hashed names and registered in the
manifest. Image optimization uses gifsicle, mozjpeg, pngquant, and SVGO (excluding
`fa-*.svg` Font Awesome icons). The output also includes Apache defaults via an
`.htaccess` file. The HTTPS development server enables hot module replacement and
live reload, watches application files for changes, and disables the error overlay.
It allows all development hosts; use it only in a trusted development environment.

Install `symfony/webpack-encore-bundle` in the application and set
`webpack_encore.output_path` to `%kernel.project_dir%/public/layout` (adjust for
your public/assets directories). Set Symfony's
`framework.assets.json_manifest_path` to that directory's `manifest.json` for
image lookups using `asset('images/example.png')`. Use
`encore_entry_script_tags('app')` and `encore_entry_link_tags('app')` in Twig for
an entry such as `layout/app.js`.

## Symfony Reprise (Vite)

The `reprise/` package provides a Vite configuration using Symfony Reprise as an
alternative to Symfony Webpack Encore. The two integrations cannot be used alongside
each other: choose either `webpack/` or `reprise/` for your application. When switching
to Reprise, replace the Encore dependency, scripts, configuration, Symfony bundle,
and Twig entry helpers with their Reprise equivalents described below.

Install it in your application's `package.json`:

```json
{
    "devDependencies": {
        "@terminal42/contao-build-tools": "file:vendor/terminal42/contao-build-tools/reprise"
    },
    "scripts": {
        "dev": "vite",
        "build": "vite build"
    }
}
```

Run `npm install`, then create `vite.config.mjs`:

```js
import Reprise from '@terminal42/contao-build-tools';

export default Reprise().getViteConfig();
```

The helper supports Encore-style chaining; `getViteConfig()` returns the final
configuration asynchronously. Pass a different assets directory as the first
argument, or `false` as the second to disable automatic entries:

```js
import Reprise from '@terminal42/contao-build-tools';

export default Reprise('layout', false)
    .addEntry('app', './layout/app.js')
    .enableSourceMaps(false)
    .configureDevServerOptions(options => { options.port = 5174; })
    .configureVite(config => { config.build.target = 'es2022'; })
    .getViteConfig();
```

Use `setOutputPath()` and `setPublicPath()` to keep Vite and Symfony paths aligned,
and `addPlugin()` to append Vite plugins. Configuration callbacks run in call order
after defaults and plugins are created; use the path setters rather than changing
paths in `configureVite()`.

Like the Encore helper, it detects top-level `layout/*.js` entries (excluding
`_`-prefixed files), outputs into the Composer `extra.public-dir` (falling back to
`web` if present, otherwise `public`), and uses `/layout/` as the public path.
It enables Sass, source maps, output cleanup, default Autoprefixer/cssnano settings
unless the application has a PostCSS configuration, HTTPS development, and reloads
when application files change. Images in `layout/images/` are copied with hashed
names and registered in the manifest. Build images are optimized using
`vite-plugin-image-optimizer` with Sharp and SVGO (excluding `fa-*.svg` Font Awesome
icons, as in Encore). The output also includes `layout/.htaccess`
or the bundled Apache defaults.

Vite uses native ES modules and production content hashing; this helper does not
add Encore's Babel/core-js polyfills. Configure additional
Vite plugins if your application needs these. Development host access remains
restricted by Vite's defaults; configure `server.allowedHosts` when needed.

Install `symfony/reprise` in the application and set its `output_path` to
`%kernel.project_dir%/public/layout` (adjust for your public/assets directories).
Set Symfony's `framework.assets.json_manifest_path` to that directory's
`manifest.json` for image lookups using `asset('images/example.png')`. Use
`reprise_entry_script_tags('app')` and `reprise_entry_link_tags('app')` in Twig.
See the [Reprise documentation](https://github.com/symfony/reprise/blob/main/doc/index.rst)
for bundle configuration. Node.js must satisfy the package's `engines` requirement.

## Deploying Contao websites

We use [Deployer](Deployer) do deploy Contao website to live servers.

To use the Deployer helper, you first need to require Deployer in your `composer.json`

```json
{
    "require-dev": {
        "terminal42/contao-build-tools": "dev-main",
        "deployer/deployer": "^7.0"
    }
}
```

**Example `deploy.php`**

```php
<?php

require_once 'vendor/terminal42/contao-build-tools/src/Deployer.php';

use Terminal42\ContaoBuildTools\Deployer;

(new Deployer('example.org', 'ssh-user', '/path/to/php'))
    ->addTarget('prod', '/path/to/deployment', 'https://example.org')
    ->buildAssets()
    ->includeSystemModules()
    ->addUploadPaths(
        // some additional directory
    )
    ->run()
;
```


[Deployer]: https://deployer.org
[SFBP]: https://symfony.com/doc/current/best_practices.html
[SBPB]: https://symfony.com/doc/current/bundles/best_practices.html
