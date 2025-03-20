<?php

declare(strict_types=1);

namespace Terminal42\ContaoBuildTools;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Deployer\Host\Host;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use function Deployer\currentHost;
use function Deployer\error;
use function Deployer\get;
use function Deployer\host;
use function Deployer\run;
use function Deployer\runLocally;
use function Deployer\set;
use function Deployer\task;
use function Deployer\test;
use function Deployer\upload;
use function Deployer\warning;

class Deployer
{
    public const MAINTENANCE_NONE = 0;
    public const MAINTENANCE_ENABLE = 1;
    public const MAINTENANCE_DISABLE = 2;
    public const MAINTENANCE_BOTH = 3;
    private const MAINTENANCE_IF_MIGRATIONS = 4;
    public const MAINTENANCE_IF_MIGRATIONS_ENABLE = 5;
    public const MAINTENANCE_IF_MIGRATIONS_DISABLE = 6;
    public const MAINTENANCE_IF_MIGRATIONS_BOTH = 7;

    // Deployer setup
    private bool $lockDeployment = true;
    private int $timeout = 300;
    private int $keepReleases = 10;

    // Custom tasks
    private bool $clearOpcache = false;
    private bool $clearHttpCache = true;
    private bool|null $installContaoManager = null;
    private bool|null $lockContaoManager = null;
    private bool $lockInstallTool = true;
    private bool $dumpEnvLocal = true;
    private int $useMaintenanceMode = self::MAINTENANCE_IF_MIGRATIONS_BOTH;
    private bool $migrateDatabase = true;
    private bool $migrateDatabaseWithDeletes = false;
    private string|null $buildAssets = null;

    // Files and folders
    private bool $includeSystemModules = false;
    private array $addUploadPaths = [];
    private array $removeUploadPaths = [];
    private array $sharedDirs = [];
    private array $sharedFiles = [];

    public function __construct(private string|null $hostname = null, private string|null $remoteUser = null, string $phpBinary = null)
    {
        require_once __DIR__.'/../deployer/common.php';

        if (null !== $phpBinary) {
            set('bin/php', $phpBinary);
        }

        task('error:run', static function () {
            error('Please call Deployer::run() after configuration is complete.');
        });

        $this->reset();
    }

    public function addTarget(string $name, string $path, string $publicURL = null, string $htaccessFile = null): self
    {
        $host = host($name)->setDeployPath($path);

        if (null !== $this->hostname) {
            $host->setHostname($this->hostname);
        }

        if (null !== $this->remoteUser) {
            $host->setRemoteUser($this->remoteUser);
        }

        if (null !== $publicURL) {
            $this->clearOpcache = true;

            if (preg_match('{^https?://}i', $publicURL)) {
                $host->set('public_url', rtrim($publicURL, '/'));
            } else {
                $host->set('opcache_command', $publicURL);
            }
        }

        if (null === $htaccessFile && is_file(rtrim(get('public_path'), '/').'/.htaccess_'.$name)) {
            $htaccessFile = '.htaccess_'.$name;
        }

        if (null !== $htaccessFile) {
            $host->set('htaccess_filename', $htaccessFile);
        }

        return $this->reset();
    }

    public function addHost(Host $host): self
    {
        // nothing to do here

        return $this->reset();
    }

    public function addUploadPaths(string|\Closure ...$paths): self
    {
        $this->addUploadPaths = array_merge($this->addUploadPaths, $paths);

        return $this->reset();
    }

    public function removeUploadPaths(string|\Closure ...$paths): self
    {
        $this->removeUploadPaths = array_merge($this->removeUploadPaths, $paths);

        return $this->reset();
    }

    public function addSharedDirs(string ...$dirs): self
    {
        $this->sharedDirs = array_unique(array_merge($this->sharedDirs, $dirs));

        return $this;
    }

    public function addSharedFiles(string ...$files): self
    {
        $this->sharedFiles = array_unique(array_merge($this->sharedFiles, $files));

        return $this;
    }

    public function includeSystemModules(bool $include = true): self
    {
        $this->includeSystemModules = $include;

        return $this->reset();
    }

    public function keepHttpCache(bool $keep = true): self
    {
        $this->clearHttpCache = !$keep;

        return $this->reset();
    }

    public function installContaoManager(bool|null $install = true, bool|null $lock = true): self
    {
        $this->installContaoManager = $install;
        $this->lockContaoManager = $lock;

        return $this->reset();
    }

    public function lockInstallTool(bool $lock = true): self
    {
        $this->lockInstallTool = $lock;

        return $this->reset();
    }

    public function dumpEnvLocal(bool $dumpEnvLocal = true): self
    {
        $this->dumpEnvLocal = $dumpEnvLocal;

        return $this->reset();
    }

    public function migrateDatabase(bool $migrate = true, bool $withDeletes = false): self
    {
        $this->migrateDatabase = $migrate;
        $this->migrateDatabaseWithDeletes = $withDeletes;

        return $this->reset();
    }

    public function lockDeployment(bool $lock = true): self
    {
        $this->lockDeployment = $lock;

        return $this->reset();
    }

    public function useMaintenanceMode(bool|int $maintenanceMode = true): self
    {
        if (is_bool($maintenanceMode)) {
            $this->useMaintenanceMode = $maintenanceMode ? self::MAINTENANCE_BOTH : self::MAINTENANCE_NONE;
        } else {
            $this->useMaintenanceMode = $maintenanceMode;
        }

        return $this->reset();
    }

    public function buildAssets(string $publicDir = 'layout', string|null $buildCommand = null): self
    {
        if (null === $buildCommand) {
            $buildCommand = file_exists('./yarn.lock') ? 'yarn run build' : 'npm run build';
        }

        $this->addUploadPaths[] = rtrim(get('public_path'), '/').'/'.ltrim($publicDir, '/');
        $this->buildAssets = $buildCommand;

        return $this;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this->reset();
    }

    public function setKeepReleases(int $keepReleases): self
    {
        $this->keepReleases = $keepReleases;

        return $this->reset();
    }

    public function run(): self
    {
        require_once('./vendor/autoload.php');

        set('keep_releases', $this->keepReleases);
        set('default_timeout', $this->timeout);
        set('allow_anonymous_stats', false);

        set('shared_dirs', $this->getSharedDirs());
        set('shared_files', $this->getSharedFiles());

        set('bin/composer', function () {
            if (test('[ -f {{deploy_path}}/.dep/composer.phar ]')) {
                run('{{bin/php}} {{deploy_path}}/.dep/composer.phar self-update');

                return '{{bin/php}} {{deploy_path}}/.dep/composer.phar';
            }

            run("cd {{deploy_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}");
            run('mv {{deploy_path}}/composer.phar {{deploy_path}}/.dep/composer.phar');

            return '{{bin/php}} {{deploy_path}}/.dep/composer.phar';
        });

        set('writable_dirs', [
            'var',
            'var/cache',
            'var/logs',
            'system/tmp'
        ]);

        task('deploy:upload', $this->uploadClosure());
        task('deploy', $this->deployBody());

        return $this;
    }

    private function reset(): self
    {
        // Will stop Deployer from running anything if self::run() is not called
        task('deploy', ['error:run']);

        return $this;
    }

    private function uploadClosure(): \Closure
    {
        $paths = [
            'config',
            'contao',
            'src',
            'templates',
            'translations',
            rtrim(get('public_path'), '/').'/favicon.ico',
            '.env',
            'composer.json',
            'composer.lock',
        ];

        foreach ($paths as $k => $v) {
            if (!(new Filesystem())->exists($v)) {
                unset ($paths[$k]);
            }
        }

        if ($this->includeSystemModules) {
            $paths = array_merge($paths, $this->getSystemModulesPaths());
        }

        return function () use ($paths) {
            $localPaths = array_values($paths);

            $localPaths = array_merge($localPaths, $this->pathClosure($this->addUploadPaths));
            $localPaths = array_diff($localPaths, $this->pathClosure($this->removeUploadPaths));

            if ($htaccess = currentHost()->get('htaccess_filename')) {
                $localPaths[] = rtrim(get('public_path'), '/').'/'.$htaccess;
            }

            $localPaths = array_unique($localPaths);

            foreach ($localPaths as $path) {
                upload($path, '{{release_path}}/', [
                    'options' => ['--recursive', '--relative'],
                    'progress_bar' => false,
                ]);
            }
        };
    }

    private function deployBody(): array
    {
        $body = [];

        if (null !== $this->buildAssets) {
            task('deploy:build-assets', function () {
                runLocally($this->buildAssets);
            })->once();

            $body[] = 'deploy:build-assets';
        }

        $body[] = 'deploy:info';
        $body[] = 'deploy:setup';

        if ($this->lockDeployment) {
            $body[] = 'deploy:lock';
        }

        $body[] = 'deploy:release';
        $body[] = 'deploy:upload';
        $body[] = 'deploy:shared';
        $body[] = 'deploy:vendors';
        $body[] = 'deploy:htaccess';

        if ($this->dumpEnvLocal) {
            task('deploy:dump-env-local', function () {
                if (!str_contains(run('{{bin/console}} list {{console_options}}'), 'dotenv:dump')) {
                    warning('Cannot dump .env.local.php, dotenv:dump command is not registered - skipping');
                } else {
                    run('{{bin/console}} dotenv:dump {{console_options}}');
                }
            });
            $body[] = 'deploy:dump-env-local';
        }

        if (true === $this->installContaoManager) {
            $body[] = 'contao:manager:download';
        }

        if (false !== $this->installContaoManager && true === $this->lockContaoManager) {
            $body[] = 'contao:manager:lock';
        }

        if ($this->lockInstallTool && !$this->isContao('^5.0')) {
            $body[] = 'contao:install:lock';
        }

        set('skip_migrations', false);

        if ($this->useMaintenanceMode & self::MAINTENANCE_IF_MIGRATIONS) {
            $body[] = 'contao:migrate:check';
        }

        if ($this->useMaintenanceMode & self::MAINTENANCE_ENABLE) {
            $body[] = 'contao:maintenance:enable'.($this->useMaintenanceMode & self::MAINTENANCE_IF_MIGRATIONS ? '-if-migrations' : '');
        }

        $body[] = 'deploy:symlink';

        if (false !== $this->installContaoManager && null === $this->lockContaoManager) {
            $body[] = 'contao:manager:auto-lock';
        }

        if ($this->clearOpcache) {
            $body[] = 'deploy:opcache';
        }

        if ($this->migrateDatabase) {
            $body[] = 'contao:migrate';

            if ($this->migrateDatabaseWithDeletes) {
                // TODO: implement contao:migrate --with-deletes
                warning('contao:migrate --with-deletes is not yet implemented');
            }
        }

        if ($this->useMaintenanceMode & self::MAINTENANCE_DISABLE) {
            $body[] = 'contao:maintenance:disable'.($this->useMaintenanceMode & self::MAINTENANCE_IF_MIGRATIONS ? '-if-migrations' : '');
        }

        if ($this->lockDeployment) {
            $body[] = 'deploy:unlock';
        }

        if ($this->clearHttpCache) {
            $body[] = 'deploy:httpcache';
        }

        $body[] = 'deploy:cleanup';
        $body[] = 'deploy:success';

        return $body;
    }

    private function getSharedDirs(): array
    {
        $composerConfig = json_decode(file_get_contents('./composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $assetsDir = $composerConfig['extra']['contao-component-dir'] ?? 'assets';

        $sharedDirs = $this->sharedDirs;

        // Contao 4.13 (might already be defined in the default contao.php recipe but we want to be independent here)
        $sharedDirs[] = Path::join($assetsDir, 'images'); // image thumbnails
        $sharedDirs[] = 'files'; // file uploads
        $sharedDirs[] = '{{public_path}}/share'; // share directory for news sitemaps
        $sharedDirs[] = 'system/config'; // old config files like localconfig.php, dcaconfig.php etc.
        $sharedDirs[] = 'system/tmp'; // some extensions and even the core still upload to system/tmp
        $sharedDirs[] = 'var/backups'; // contao:database:backup directory
        $sharedDirs[] = 'var/logs'; // logs directory

        // Add or remove contao-manager directory
        if (false !== $this->installContaoManager) {
            $sharedDirs[] = 'contao-manager';
        }

        if (isset($composerConfig['require']['isotope/isotope-core'])) {
            $sharedDirs[] = 'isotope';
        }

        if (isset($composerConfig['require']['terminal42/contao-avatar'])) {
            $sharedDirs[] = Path::join($assetsDir, 'avatars');
        }

        if (
            InstalledVersions::isInstalled('terminal42/notification_center')
            && InstalledVersions::satisfies(new VersionParser(), 'terminal42/notification_center', '^2.0')
        ) {
            $sharedDirs[] = 'var/nc_bulky_items';
        }

        if ($this->isContao('^5.0')) {
            $sharedDirs[] = 'var/deferred-images'; // Deferred image meta data
            $sharedDirs[] = Path::join($assetsDir, 'previews'); // File preview thumbnails
        }

        if ($this->isContao('^5.5')) {
            $sharedDirs[] = 'var/loupe'; // loupe directory for the backend search engine
        }

        return array_unique($sharedDirs);
    }

    private function getSharedFiles(): array
    {
        $sharedFiles = $this->sharedFiles;

        if (null === $this->installContaoManager) {
            $sharedFiles[] = '{{public_path}}/contao-manager.phar.php';
        }

        foreach (['yaml', 'php', 'xml', 'yml'] as $ext) {
            if ((new Filesystem())->exists('config/parameters.'.$ext)) {
                $sharedFiles[] = 'config/parameters.'.$ext;
                break;
            }
        }

        $sharedFiles[] = '.env.local';

        // If a composer auth is required, and it exists locally,
        // we can assume it also needs to exist on the server as a shared file
        if ((new Filesystem())->exists('auth.json')) {
            $sharedFiles[] = 'auth.json';
        }

        return array_unique($sharedFiles);
    }

    private function getSystemModulesPaths(): array
    {
        $paths = [];

        // Upload all system/modules that are not .gitignore'd
        $gitignore = file('.gitignore') ?: [];
        $unignore = \in_array('/system/modules/*', $gitignore, true);
        foreach (scandir('system/modules') as $folder) {
            if (
                '.' === $folder
                || '..' === $folder
                || \in_array(($unignore ? '!' : '').'/system/modules/'.$folder, $gitignore, true) !== $unignore
            ) {
                continue;
            }

            $paths[] = 'system/modules/'.$folder;
        }

        return $paths;
    }

    private function pathClosure(array $paths): array
    {
        $result = [];

        foreach ($paths as $path) {
            if (!$path instanceof \Closure) {
                $result[] = $path;
                continue;
            }

            $result = [...$result, ...(array) $path()];
        }

        return $result;
    }

    private function isContao(string $requirement): bool
    {
        return InstalledVersions::satisfies(new VersionParser(), 'contao/core-bundle', $requirement);
    }
}
