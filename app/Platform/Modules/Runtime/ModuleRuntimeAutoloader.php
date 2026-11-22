<?php

namespace App\Platform\Modules\Runtime;

class ModuleRuntimeAutoloader
{
    /** @var array<string, true> */
    private static array $registered = [];

    /**
     * Bundled third-party classes, per module: the classmap file and, once a
     * class was first looked up, its contents.
     *
     * @var array<string, array{classmap: string, map: array<string, string>|null}>
     */
    private static array $bundles = [];

    private static bool $bundleLoaderRegistered = false;

    /** @var array<string, true> */
    private static array $loadedFiles = [];

    /**
     * Register module PSR-4 prefixes before Laravel registers package providers.
     *
     * This intentionally uses native filesystem functions because bootstrap/app.php
     * calls it before the service container and facades exist.
     */
    public static function registerInstalledModules(?string $modulesPath = null): void
    {
        $modulesPath ??= dirname(__DIR__, 4).'/Modules';

        if (! is_dir($modulesPath)) {
            return;
        }

        $directories = glob(rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $name = basename($directory);

            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1 && is_file($directory.'/module.json')) {
                self::register($name, $modulesPath);
            }
        }
    }

    public static function register(string $name, ?string $modulesPath = null): void
    {
        $modulesPath ??= dirname(__DIR__, 4).'/Modules';
        $prefix = "Modules\\{$name}\\";
        $base = rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR;
        $registration = $prefix.$base;

        // Registering again (after an install swapped the directory) must see
        // the new bundle, so this runs before the PSR-4 short-circuit.
        self::registerBundle($name, $modulesPath);

        if (isset(self::$registered[$registration])) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($prefix, $base): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            if ($relative === false || str_contains($relative, '..')) {
                return;
            }

            $path = $base.str_replace('\\', '/', $relative).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        });

        self::$registered[$registration] = true;
    }

    /**
     * Forget a module's cached classmap after its files changed underneath
     * this process (install, upgrade, restore, uninstall), so the next lookup
     * reads what is on disk now.
     */
    public static function refresh(string $name, ?string $modulesPath = null): void
    {
        $classmap = self::$bundles[$name]['classmap'] ?? null;
        unset(self::$bundles[$name]);

        if ($classmap !== null && function_exists('opcache_invalidate')) {
            @opcache_invalidate($classmap, true);
        }

        self::registerBundle($name, $modulesPath ?? dirname(__DIR__, 4).'/Modules');
    }

    /**
     * Load a bundled module's Composer `files` entries, once per process.
     *
     * Called when nwidart registers an enabled module, so a disabled module's
     * files never run.
     */
    public static function loadBundleFiles(string $name, ?string $modulesPath = null): void
    {
        if (isset(self::$loadedFiles[$name])) {
            return;
        }

        $bundle = rtrim($modulesPath ?? dirname(__DIR__, 4).'/Modules', DIRECTORY_SEPARATOR).'/'.$name.'/bundle';
        $manifest = is_file($bundle.'/bundle.json') ? json_decode((string) file_get_contents($bundle.'/bundle.json'), true) : null;
        $files = is_array($manifest) && is_array($manifest['files'] ?? null) ? $manifest['files'] : [];

        self::$loadedFiles[$name] = true;
        foreach ($files as $file) {
            if (is_string($file) && ! str_contains($file, '..') && is_file($bundle.'/'.$file)) {
                require_once $bundle.'/'.$file;
            }
        }
    }

    public static function hasLoadedFiles(string $name): bool
    {
        return isset(self::$loadedFiles[$name]);
    }

    /**
     * Look a class up in the bundled classmaps. Registered after Composer's
     * loader, so a class the host ships always resolves to the host's copy.
     */
    public static function loadBundledClass(string $class): void
    {
        foreach (self::$bundles as $name => $bundle) {
            if ($bundle['map'] === null) {
                $bundle['map'] = self::readClassmap($bundle['classmap']);
                self::$bundles[$name] = $bundle;
            }

            $relative = $bundle['map'][$class] ?? null;
            if ($relative !== null) {
                $path = dirname($bundle['classmap']).'/'.$relative;
                if (! str_contains($relative, '..') && is_file($path)) {
                    require_once $path;
                }

                return;
            }
        }
    }

    private static function registerBundle(string $name, string $modulesPath): void
    {
        $classmap = rtrim($modulesPath, DIRECTORY_SEPARATOR).'/'.$name.'/bundle/classmap.php';
        if (! is_file($classmap)) {
            unset(self::$bundles[$name]);

            return;
        }

        self::$bundles[$name] = ['classmap' => $classmap, 'map' => null];

        if (! self::$bundleLoaderRegistered) {
            spl_autoload_register([self::class, 'loadBundledClass']);
            self::$bundleLoaderRegistered = true;
        }
    }

    /** @return array<string, string> */
    private static function readClassmap(string $path): array
    {
        try {
            $map = is_file($path) ? require $path : [];
        } catch (\Throwable) {
            return [];
        }

        return is_array($map) ? $map : [];
    }
}
