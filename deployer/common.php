<?php

use function Deployer\askConfirmation;
use function Deployer\currentHost;
use function Deployer\get;
use function Deployer\has;
use function Deployer\info;
use function Deployer\invoke;
use function Deployer\run;
use function Deployer\set;
use function Deployer\task;
use function Deployer\test;
use function Deployer\warning;

require_once 'recipe/contao.php';

// Task: clear opcache
task('deploy:opcache', static function () {
    if (has('opcache_command')) {
        try {
            run('{{opcache_command}}');
            return;
        } catch (\Exception $e) {
            // Ignore failing "pkill lsphp" on Cyon, the process does not always exist
            if ('pkill lsphp' === get('opcache_command')) {
                return;
            }

            warning($e->getMessage());

            if (!askConfirmation('Clearing the PHP OPcache failed, continue deployment?')) {
                exit(1);
            }
        }
    }

    if (!has('public_url')) {
        info(' … skipped');
        return;
    }

    run('cd {{release_path}} && echo "<?php opcache_reset(); clearstatcache(true);" > {{public_path}}/opcache.php && curl -sL {{public_url}}/opcache.php && rm {{public_path}}/opcache.php');
});

// Task: clear HTTP cache
task('deploy:httpcache', static function () {
    if (has('previous_release') && test('[ -d {{previous_release}}/var/cache/prod/http_cache ]')) {
        try {
            run('rm -rf {{previous_release}}/var/cache/prod/http_cache');
        } catch (\Exception $e) {
            warning(' … unable to remove all directories.');
        }
    }
});

// Task: Composer self update
task('deploy:composer-self-update', static function () {
    run('{{bin/composer}} self-update');
});

// Task: Lock Contao Manager if there is no users
task('contao:manager:auto-lock', static function () {
    if (test('[ -f {{release_or_current_path}}/contao-manager/users.json ]')) {
        info(' … user.json found');
        return;
    }

    invoke('contao:manager:lock');
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

task('contao:migrate', function () {
    if (get('skip_migrations')) {
        info(' … skipped');
        return;
    }

    try {
        run('{{bin/console}} contao:migrate {{console_options}}');
    } catch (\Exception $e) {
        warning($e->getMessage());

        if (!askConfirmation('Database migration failed, continue deployment?')) {
            exit(1);
        }
    }
});

task('contao:migrate:check', function () {
    if (!str_contains(run('{{bin/console}} contao:migrate --dry-run {{console_options}}'), 'Pending')) {
        info(' … no migrations found, skipping maintenance mode & migrations');
        set('skip_migrations', true);
    }
});

task('contao:maintenance:enable-if-migrations', function () {
    if (get('skip_migrations')) {
        info(' … skipped');
        return;
    }

    invoke('contao:maintenance:enable');
});

task('contao:maintenance:disable-if-migrations', function () {
    if (get('skip_migrations')) {
        info(' … skipped');
        return;
    }

    invoke('contao:maintenance:disable');
});
