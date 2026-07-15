<?php

declare(strict_types=1);

namespace Terminal42\ContaoBuildTools;

final class BuildToolsConfig
{
    private const LEGACY_MODULES = './system/modules';

    private array $config;

    public function __construct(string $rootDirectory)
    {
        $this->config = ['directories' => ['.']];
        $configFile = rtrim($rootDirectory, '/').'/build-tools.php';

        if (file_exists($configFile)) {
            $config = require $configFile;
            if (!\is_array($config)) {
                throw new \UnexpectedValueException('The build-tools.php configuration must return an array.');
            }

            $this->config = array_replace($this->config, $config);
        }

        if (!isset($this->config['directories']) || !\is_array($this->config['directories'])) {
            throw new \UnexpectedValueException('The build-tools.php configuration must contain a "directories" array.');
        }

        foreach ($this->config['directories'] as $directory) {
            if (!\is_string($directory) || '' === trim($directory)) {
                throw new \UnexpectedValueException('Every build-tools.php directory must be a non-empty string.');
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return list<string>
     */
    public function getDirectories(): array
    {
        return array_values($this->config['directories']);
    }

    /**
     * @param array<string|int, string> $paths
     */
    public function filterPaths(array $paths, bool $prefix = true): array
    {
        $result = [];
        $directories = $prefix ? $this->getDirectories() : ['.'];

        foreach ($directories as $directory) {
            foreach ($paths as $source => $path) {
                $file = $this->prefixPath($directory, \is_string($source) ? $source : $path);
                $path = $this->prefixPath($directory, $path);

                if (!file_exists($file) && (!str_contains($file, '*') || !glob($file))) {
                    continue;
                }

                if ($this->prefixPath($directory, self::LEGACY_MODULES) === $path) {
                    foreach (scandir($path) as $dir) {
                        if ('.' !== $dir && '..' !== $dir && is_dir($path.'/'.$dir) && !is_link($path.'/'.$dir)) {
                            $result[] = $path.'/'.$dir;
                        }
                    }

                    continue;
                }

                $result[] = $path;
            }
        }

        return $result;
    }

    public function prefixPath(string $directory, string $path): string
    {
        $directory = rtrim($directory, '/');

        if ('' === $directory || '.' === $directory) {
            return $path;
        }

        return './'.trim($directory, '/').'/'.ltrim($path, './');
    }
}
