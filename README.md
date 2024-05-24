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
