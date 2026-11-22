<?php

namespace App\Platform\Modules\Runtime;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use InvalidArgumentException;
use InvoiceShelf\Modules\Manifest\BundleManifest;
use InvoiceShelf\Modules\Manifest\BundleValidator;
use InvoiceShelf\Modules\Manifest\ModuleManifest;
use Throwable;

/**
 * Whether a module's bundle/ fits this host: the host packages it relies on
 * are installed at versions it accepts, its packages run on this PHP, and
 * nothing it ships (package, class or function) is already provided by the
 * host or by another module on disk.
 *
 * Checked at marketplace install, local install, enable and on every start,
 * so a host upgrade cannot leave a bundled module loading against packages
 * it no longer fits.
 */
final class ModuleBundle
{
    /** How many colliding classes to name before summarising. */
    private const REPORTED_CLASSES = 3;

    /**
     * @param  array<string, string>  $classmap
     */
    private function __construct(
        private readonly string $name,
        private readonly BundleManifest $manifest,
        private readonly array $classmap,
    ) {}

    /**
     * Why the module's bundle does not fit; empty when it fits or when the
     * module bundles nothing.
     *
     * @return list<string>
     */
    public static function problemsFor(string $modulePath): array
    {
        if (! self::declaresBundle($modulePath)) {
            return [];
        }

        try {
            $bundle = self::read($modulePath);
        } catch (InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }

        return [
            ...$bundle->hostPackageProblems(),
            ...$bundle->phpProblems(),
            ...$bundle->hostCollisions(),
            ...$bundle->moduleCollisions(),
        ];
    }

    /** Whether module.json says the module ships its own dependencies. */
    public static function declaresBundle(string $modulePath): bool
    {
        $metadata = json_decode((string) @file_get_contents($modulePath.'/module.json'), true);

        return is_array($metadata) && ($metadata['dependency_policy'] ?? null) === ModuleManifest::POLICY_BUNDLED;
    }

    private static function read(string $modulePath): self
    {
        $bundle = $modulePath.'/'.BundleManifest::DIRECTORY;

        return new self(
            basename($modulePath),
            BundleManifest::fromFile($bundle.'/'.BundleManifest::FILE),
            BundleValidator::classmap($bundle),
        );
    }

    /** @return list<string> */
    private function hostPackageProblems(): array
    {
        $problems = [];
        $parser = new VersionParser;
        foreach ($this->manifest->hostPackages as $package => $requirers) {
            if (! InstalledVersions::isInstalled($package)) {
                $problems[] = "It needs {$package}, which this InvoiceShelf does not ship.";

                continue;
            }
            foreach ($requirers as $requirer => $constraint) {
                try {
                    $satisfied = InstalledVersions::satisfies($parser, $package, $constraint);
                } catch (Throwable) {
                    $satisfied = false;
                }
                if (! $satisfied) {
                    $installed = InstalledVersions::getPrettyVersion($package) ?? 'another version';
                    $problems[] = "{$requirer} needs {$package} {$constraint}, but this InvoiceShelf has {$installed}.";
                }
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function phpProblems(): array
    {
        $problems = [];
        foreach ($this->manifest->packages as $package => $details) {
            if ($details['php'] !== null && ! ModuleCompatibility::satisfies(PHP_VERSION, $details['php'])) {
                $problems[] = "{$package} needs PHP {$details['php']}.";
            }
        }

        return $problems;
    }

    /**
     * A bundled package, class or function the host already has would either
     * be shadowed (classes) or fatal on load (functions).
     *
     * @return list<string>
     */
    private function hostCollisions(): array
    {
        $problems = [];
        foreach (array_keys($this->manifest->packages) as $package) {
            // Development packages are only on a developer's machine, where the
            // host's copy winning is expected and harmless.
            if (InstalledVersions::isInstalled($package, false)) {
                $problems[] = "{$package} is bundled, but InvoiceShelf already ships it.";
            }
        }

        $classes = array_values(array_filter(array_keys($this->classmap), fn (string $class): bool => self::hostCanLoad($class)
            && ! self::isDevelopmentClass($class, $this->classmap[$class])));
        if ($classes !== []) {
            $problems[] = self::describeClasses($classes, 'InvoiceShelf already has');
        }

        if (! ModuleRuntimeAutoloader::hasLoadedFiles($this->name)) {
            foreach ($this->manifest->functions as $function) {
                if (function_exists($function)) {
                    $problems[] = "The bundle declares {$function}(), which already exists.";
                }
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function moduleCollisions(): array
    {
        $problems = [];
        foreach (glob(base_path('Modules').'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $other = basename($directory);
            if ($other === $this->name || preg_match('/^[A-Z][A-Za-z0-9]*$/', $other) !== 1 || ! self::declaresBundle($directory)) {
                continue;
            }
            try {
                $theirs = self::read($directory);
            } catch (InvalidArgumentException) {
                continue; // their own problem, reported when they are checked
            }

            // The same release of a package in two modules is the same code; any
            // other overlap means one module would run against the other's copy.
            $shared = [];
            foreach ($this->manifest->packages as $package => $details) {
                $their = $theirs->manifest->packages[$package] ?? null;
                if ($their === null) {
                    continue;
                }
                if ($their['version'] === $details['version'] && $their['reference'] === $details['reference']) {
                    $shared[] = $package.'/';

                    continue;
                }
                $problems[] = "{$package} {$details['version']} is bundled, but {$other} bundles {$their['version']}.";
            }

            $classes = [];
            foreach ($this->classmap as $class => $path) {
                if (isset($theirs->classmap[$class]) && ! self::inPackages($path, $shared)) {
                    $classes[] = $class;
                }
            }
            if ($classes !== []) {
                $problems[] = self::describeClasses($classes, "{$other} also bundles");
            }

            // Function names are case-insensitive in PHP.
            $functions = array_uintersect($this->manifest->functions, $theirs->manifest->functions, 'strcasecmp');
            foreach ($functions as $function) {
                $problems[] = "{$function}() is also declared by {$other}.";
            }
        }

        return $problems;
    }

    /** Whether one of the host's Composer autoloaders would load the class. */
    private static function hostCanLoad(string $class): bool
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if ($loader->findFile($class) !== false) {
                return true;
            }
        }

        return false;
    }

    /** A class the host loads from one of its development packages, which production never has. */
    private static function isDevelopmentClass(string $class, string $path): bool
    {
        $parts = explode('/', $path);
        $package = ($parts[0] ?? '').'/'.($parts[1] ?? '');

        return InstalledVersions::isInstalled($package) && ! InstalledVersions::isInstalled($package, false);
    }

    /** @param list<string> $packages */
    private static function inPackages(string $path, array $packages): bool
    {
        foreach ($packages as $package) {
            if (str_starts_with($path, $package)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $classes */
    private static function describeClasses(array $classes, string $holder): string
    {
        $named = implode(', ', array_slice($classes, 0, self::REPORTED_CLASSES));
        $more = count($classes) > self::REPORTED_CLASSES ? ' and '.(count($classes) - self::REPORTED_CLASSES).' more' : '';

        return "The bundle ships {$named}{$more}, which {$holder}.";
    }
}
