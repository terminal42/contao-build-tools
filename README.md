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

### Rector

The `rector` script will automatically upgrade the code to match the latest Contao standards.

### PHPStan

The `phpstan` script will check your code with PHPStan.
Create a `phpstan.neon` file in your project to extend the default configuration. 


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
    ecs:
        name: build-tools
        runs-on: ubuntu-latest
        steps:
            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: 8.1
                  extensions: dom, fileinfo, filter, gd, hash, intl, json, mbstring, mysqli, pcre, pdo_mysql, zlib
                  coverage: none

            - name: Checkout
              uses: actions/checkout@v3

            - name: Install the dependencies
              run: |
                  composer install --no-interaction --no-progress

            - name: Run all build-tools to validate code
              run: composer run build-tools

```



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


## Error tracking with [Sentry.io][Sentry]

The `Terminal42\ContaoBuildTools\ErrorHandlingTrait` adds useful Sentry helpers.

 - `ErrorHandlingTrait::sentryOrThrow` will either log an error/exception to sentry,
    or it will throw an exception if Sentry integration is not available (e.g. on localhost
    or in `dev` environment). It is mostly useful when running looping cronjobs, like 
    synchronizing Contao with a remote system, so an error on syncing a record will not prevent
    the sync loop from finishing other records.

 - `ErrorHandlingTraig::sentryCheckIn` has been added for the new [Sentry Cron job monitoring][SentryCron].
    Call `sentryCheckIn()` without argument to start a check in, and subsequently with a boolean
    `true` or `false` after the job has successfully run or failed.


[Deployer]: https://deployer.org
[Sentry]: https://sentry.io
[SentryCron]: https://docs.sentry.io/product/crons/
[SFBP]: https://symfony.com/doc/current/best_practices.html
[SBPB]: https://symfony.com/doc/current/bundles/best_practices.html
