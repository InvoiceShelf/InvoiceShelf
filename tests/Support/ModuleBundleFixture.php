<?php

namespace Tests\Support;

use InvoiceShelf\Modules\Manifest\BundleManifest;
use InvoiceShelf\Modules\Manifest\BundleValidator;

/**
 * Writes a complete schema-2 module with dependency_policy "bundled" to disk:
 * module.json, composer.json, a provider, a cleanup class, a built asset and
 * a bundle/ the SDK validator accepts.
 */
final class ModuleBundleFixture
{
    /**
     * @param  array<string, array{version?: string, reference?: string|null, php?: string|null, classes?: list<string>}>  $packages  package => its classes
     * @param  array<string, array<string, string>>  $hostPackages  host package => requirer => constraint
     * @param  array<string, string>  $files  Composer `files` entries, relative to bundle/, => their source
     * @param  array<string, mixed>  $module  module.json overrides
     */
    public static function write(
        string $path,
        array $packages,
        array $hostPackages = [],
        array $files = [],
        array $module = [],
    ): void {
        $name = basename($path);
        $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        foreach (['app/Providers', 'app/Lifecycle', 'dist', 'bundle'] as $directory) {
            @mkdir($path.'/'.$directory, 0755, true);
        }

        file_put_contents($path.'/module.json', json_encode([
            'name' => $name, 'alias' => strtolower($name), 'description' => 'Bundled test module', 'keywords' => [], 'priority' => 0,
            'providers' => ["Modules\\{$name}\\Providers\\{$name}ServiceProvider"], 'aliases' => [], 'files' => [], 'requires' => [],
            'schema_version' => 2, 'slug' => $slug, 'version' => '1.0.0', 'license' => 'AGPL-3.0-only',
            'compatibility' => ['invoiceshelf' => '^3.0.0', 'module_api' => '^1.4.0', 'php' => '^8.4.0', 'extensions' => []],
            'module_dependencies' => [], 'migration_policy' => 'reversible',
            'uninstall' => ['data_cleanup' => "Modules\\{$name}\\Lifecycle\\DataCleanup"],
            'dependency_policy' => 'bundled', 'assets' => ['dist/app.js'],
            ...$module,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        file_put_contents($path.'/composer.json', json_encode([
            'name' => "invoiceshelf/module-{$slug}",
            'license' => 'AGPL-3.0-only',
            'require' => ['php' => '^8.4', 'invoiceshelf/modules' => '^3.5', ...array_fill_keys(array_keys($packages), '*')],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        file_put_contents($path.'/dist/app.js', 'export {}');
        file_put_contents($path."/app/Providers/{$name}ServiceProvider.php", "<?php\n\nnamespace Modules\\{$name}\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass {$name}ServiceProvider extends ServiceProvider {}\n");
        file_put_contents($path.'/app/Lifecycle/DataCleanup.php', "<?php\n\nnamespace Modules\\{$name}\\Lifecycle;\n\nuse InvoiceShelf\\Modules\\Contracts\\DataCleanup as Contract;\n\nfinal class DataCleanup implements Contract\n{\n    public function cleanup(): void {}\n}\n");

        $classmap = [];
        $entries = [];
        foreach ($packages as $package => $details) {
            $version = $details['version'] ?? '1.0.0';
            foreach ($details['classes'] ?? [] as $class) {
                $relative = $package.'/src/'.str_replace('\\', '/', $class).'.php';
                $namespace = substr($class, 0, (int) strrpos($class, '\\'));
                $short = substr($class, (int) strrpos($class, '\\') + 1);
                @mkdir(dirname($path.'/bundle/'.$relative), 0755, true);
                file_put_contents($path.'/bundle/'.$relative, "<?php\n\nnamespace {$namespace};\n\nfinal class {$short}\n{\n    public const VERSION = '{$version}';\n}\n");
                $classmap[$class] = $relative;
            }
            @mkdir($path.'/bundle/'.$package, 0755, true);
            file_put_contents($path.'/bundle/'.$package.'/composer.json', json_encode(['name' => $package, 'version' => $version], JSON_UNESCAPED_SLASHES)."\n");
            $entries[$package] = [
                'version' => $version,
                'reference' => array_key_exists('reference', $details) ? $details['reference'] : 'ref-'.$version,
                'license' => ['MIT'],
                'php' => $details['php'] ?? null,
                'extensions' => [],
            ];
        }

        $functions = [];
        foreach ($files as $relative => $source) {
            @mkdir(dirname($path.'/bundle/'.$relative), 0755, true);
            file_put_contents($path.'/bundle/'.$relative, $source);
            array_push($functions, ...BundleValidator::declaredFunctions($path.'/bundle/'.$relative));
        }
        sort($functions);

        self::writeClassmap($path, $classmap);
        file_put_contents($path.'/bundle/bundle.json', (new BundleManifest(
            str_repeat('a', 64), $entries, $hostPackages, array_keys($files), $functions,
        ))->toJson());
    }

    /** @param array<string, string> $classmap */
    public static function writeClassmap(string $path, array $classmap): void
    {
        ksort($classmap);
        $lines = ["<?php\n\nreturn [\n"];
        foreach ($classmap as $class => $relative) {
            $lines[] = '    '.var_export($class, true).' => '.var_export($relative, true).",\n";
        }
        $lines[] = "];\n";
        file_put_contents($path.'/bundle/classmap.php', implode('', $lines));
    }

    /**
     * Every file under the module as archive entries rooted at its name.
     *
     * @return array<string, string>
     */
    public static function archiveEntries(string $path): array
    {
        $name = basename($path);
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $entries[$name.'/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($path) + 1))] = (string) file_get_contents($file->getPathname());
        }
        ksort($entries);

        return $entries;
    }
}
