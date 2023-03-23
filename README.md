# terminal42 Deployer Encore

This is an experimental repository to ease configuration of [Deployer](https://deployer.org) for terminal42 Contao websites.

**DO NOT USE IN PRODUCTION**


## Example `deploy.php`

```php
<?php

require_once 'vendor/terminal42/deployer-encore/src/Deployer.php';

use Terminal42\DeployerEncore\Deployer;

(new Deployer('example.org', 'ssh-user', '/path/to/php'))
    ->addTarget('prod', '/path/to/deployment', 'https://example.org')
    ->buildAssets()
    ->includeSystemModules()
    ->addPaths(
        // some additional directory
    )
    ->run()
;
```
