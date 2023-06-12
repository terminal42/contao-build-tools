<?php

use function Deployer\currentHost;
use function Deployer\get;
use function Deployer\has;
use function Deployer\info;
use function Deployer\run;
use function Deployer\task;

require_once 'recipe/contao.php';

// Task: clear opcache
task('deploy:opcache', static function () {
    if (has('opcache_command')) {
        run('{{opcache_command}}');
        return;
    }

    if (!has('public_url')) {
        info(' … skipped');
        return;
    }

    run('cd {{release_path}} && echo "<?php opcache_reset(); clearstatcache(true);" > {{public_path}}/opcache.php && curl -sL {{public_url}}/opcache.php && rm {{public_path}}/opcache.php');
});

// Task: Composer self update
task('deploy:composer-self-update', static function () {
    run('{{bin/composer}} self-update');
});

// Task: deploy the .htaccess file
task('deploy:htaccess', static function () {
    $file = currentHost()->get('htaccess_filename');

    if (!$file) {
        info(' … skipped');
        return;
    }

    $publicPath = get('public_path');

    run("cd {{release_path}}/$publicPath && if [ -f \"./.htaccess\" ]; then rm -f ./.htaccess; fi");
    run("cd {{release_path}}/$publicPath && if [ -f \"./$file\" ]; then mv ./$file ./.htaccess; fi");
});
