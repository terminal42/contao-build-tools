<?php

declare(strict_types=1);

namespace Terminal42\DeployerEncore;

use Deployer\Host\Host;
use function Deployer\add;
use function Deployer\currentHost;
use function Deployer\error;
use function Deployer\get;
use function Deployer\host;
use function Deployer\runLocally;
use function Deployer\set;
use function Deployer\task;
use function Deployer\upload;
use function Deployer\warning;

class Deployer
{
    // Deployer setup
    private bool $lockDeployment = true;
    private int $timeout = 300;
    private int $keepReleases = 10;

    // Custom tasks
    private bool $clearOpcache = false;
    private bool $hasHtaccess = false;
    private bool $installContaoManager = true;
    private bool $lockContaoManager = true;
    private bool $lockInstallTool = true;
    private bool $migrateDatabase = true;
    private bool $migrateDatabaseWithDeletes = false;
    private string|null $buildAssets = null;

    // Files and folders
    private bool $includeSystemModules = false;
    private array $addUploadPaths = [];
    private array $removeUploadPaths = [];
    private array $sharedDirs;
    private array $sharedFiles;

    public function __construct(private string|null $hostname = null, private string|null $remoteUser = null, string $phpBinary = null)
    {
        require_once __DIR__.'/../recipe/common.php';

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
            $host->set('public_url', $publicURL);
        }

        if (null === $htaccessFile && is_file(rtrim(get('public_path'), '/').'/.htaccess_'.$name)) {
            $htaccessFile = '.htaccess_'.$name;
        }

        if (null !== $htaccessFile) {
            $this->hasHtaccess = true;
            $host->set('htaccess_filename', $htaccessFile);
        }

        return $this->reset();
    }

    public function addHost(Host $host): self
    {
        // nothing to do here

        return $this->reset();
    }

    public function addUploadPaths(string ...$paths): self
    {
        $this->addUploadPaths = array_unique(array_merge($this->addUploadPaths, $paths));

        return $this->reset();
    }

    public function removeUploadPaths(string ...$paths): self
    {
        $this->removeUploadPaths = array_unique(array_merge($this->removeUploadPaths, $paths));

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

    public function installContaoManager(bool $install = true, bool $lock = true): self
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

    public function buildsAssets(string $publicDir = 'layout', string $buildCommand = 'yarn build'): self
    {
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
        set('keep_releases', $this->keepReleases);
        set('default_timeout', $this->timeout);
        set('allow_anonymous_stats', false);

        add('shared_dirs', $this->sharedDirs);
        add('shared_files', $this->sharedFiles);

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
            if (!file_exists($v)) {
                unset ($paths[$k]);
            }
        }

        if ($this->includeSystemModules) {
            $paths = array_merge($paths, $this->getSystemModulesPaths());
        }

        $paths = array_merge($paths, $this->addUploadPaths);
        $paths = array_diff($paths, $this->removeUploadPaths);

        return static function () use ($paths) {
            $localPaths = array_values($paths);

            if ($htaccess = currentHost()->get('htaccess_filename')) {
                $localPaths[] = rtrim(get('public_path'), '/').'/'.$htaccess;
            }

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
            });

            $body[] = 'deploy:build-assets';
        }

        $body[] = 'deploy:info';
        $body[] = 'deploy:setup';

        if ($this->lockDeployment) {
            $body[] = 'deploy:lock';
        }

        $body[] = 'deploy:release';
        $body[] = 'deploy:shared';
        $body[] = 'deploy:upload';
        $body[] = 'deploy:composer-self-update';
        $body[] = 'deploy:vendors';

        if ($this->hasHtaccess) {
            $body[] = 'deploy:htaccess';
        }

        if ($this->installContaoManager) {
            $body[] = 'contao:manager:download';

            if ($this->lockContaoManager) {
                $body[] = 'contao:manager:lock';
            }
        }

        if ($this->lockInstallTool) {
            $body[] = 'contao:install:lock';
        }

        $body[] = 'contao:maintenance:enable';
        $body[] = 'deploy:symlink';

        if ($this->clearOpcache) {
            $body[] = 'deploy:opcache';
        }

        if ($this->migrateDatabase) {
            $body[] =  'contao:migrate';

            if ($this->migrateDatabaseWithDeletes) {
                // TODO: implement contao:migrate --with-deletes
                warning('contao:migrate --with-deletes is not yet implemented');
            }
        }

        $body[] =  'contao:maintenance:disable';

        if ($this->lockDeployment) {
            $body[] = 'deploy:unlock';
        }

        $body[] = 'deploy:cleanup';
        $body[] = 'deploy:success';

        return $body;
    }

    private function getSystemModulesPaths(): array
    {
        $paths = [];

        // Upload all system/modules that are not .gitignore'd
        $gitignore = file('.gitignore') ?: [];
        foreach (scandir('system/modules') as $folder) {
            if ('.' === $folder || '..' === $folder || \in_array('/system/modules/'.$folder, $gitignore, true)) {
                continue;
            }

            $paths[] = 'system/modules/'.$folder;
        }

        return $paths;
    }
}
