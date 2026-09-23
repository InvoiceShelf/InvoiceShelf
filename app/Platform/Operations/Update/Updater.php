<?php

namespace App\Platform\Operations\Update;

use App\Platform\Operations\Database\SchemaConsolidationGuard;
use App\Platform\Operations\Events\UpdateFinished;
use App\Platform\Operations\Models\Setting;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * The self-update pipeline.
 *
 * Each stage is a separate static entry point so that the HTTP controller and
 * the console command can drive them one at a time and report progress in
 * between: check -> download -> unzip -> copy -> clean -> migrate -> finish.
 */
class Updater
{
    use CallsReleaseServer;

    /**
     * Guzzle options shared by every release-server call.
     */
    private const REQUEST_OPTIONS = ['timeout' => 100, 'track_redirects' => true];

    /**
     * Ask the release server whether a newer build exists on the given channel.
     *
     * The answer is handed back as the release server phrased it, except that a
     * list of required extensions is graded against this machine first.
     */
    public static function checkForUpdate($installed_version, $updater_channel = 'stable')
    {
        $response = static::getRemote(
            sprintf('releases/update-check/%s?channel=%s', $installed_version, $updater_channel),
            self::REQUEST_OPTIONS
        );

        $answer = (object) ['success' => false, 'release' => null];

        if ($response && ($response->getStatusCode() == 200)) {
            $answer = json_decode($response->getBody()->getContents());
        }

        if ($answer->success && $answer->release && property_exists($answer->release, 'extensions')) {
            $answer->release->extensions = static::gradeRequirements(
                $answer->release->extensions,
                $answer->release->min_php_version
            );
        }

        return $answer;
    }

    /**
     * Pull the release archive into a private temporary directory.
     *
     * @return string|array|false the archive path, or a falsy/failure payload
     */
    public static function download($new_version, $is_cmd = 0)
    {
        $response = static::getRemote('releases/download/'.$new_version.'.zip', self::REQUEST_OPTIONS);

        if ($response instanceof RequestException) {
            return [
                'success' => false,
                'error' => 'Download Exception',
                'data' => [
                    'path' => null,
                ],
            ];
        }

        $archive = null;

        if ($response && ($response->getStatusCode() == 200)) {
            $archive = $response->getBody()->getContents();
        }

        $target = static::makeTempDirectory('temp-').'/upload.zip';

        if (! is_int(file_put_contents($target, $archive))) {
            return false;
        }

        return $target;
    }

    /**
     * Expand the archive into a second temporary directory and drop the zip.
     *
     * @return string the directory holding the extracted release
     *
     * @throws \Exception when the archive is gone
     */
    public static function unzip($zip_file_path)
    {
        if (! file_exists($zip_file_path)) {
            throw new \Exception('Zip file not found');
        }

        $destination = static::makeTempDirectory('temp2-');

        $archive = new ZipArchive;

        if ($archive->open($zip_file_path)) {
            $archive->extractTo($destination);
        }

        $archive->close();

        File::delete($zip_file_path);

        return $destination;
    }

    /**
     * Overlay the extracted release on top of this installation.
     */
    public static function copyFiles($temp_extract_dir)
    {
        if (! File::copyDirectory($temp_extract_dir.'/InvoiceShelf', base_path())) {
            return false;
        }

        File::deleteDirectory($temp_extract_dir);

        return true;
    }

    /**
     * Legacy clean-up: remove exactly the paths the release server listed.
     *
     * @param  string  $json  JSON array of installation-relative paths
     */
    public static function deleteFiles($json)
    {
        foreach (json_decode($json) as $relative) {
            File::delete(base_path($relative));
        }

        return true;
    }

    /**
     * Manifest clean-up: drop everything the shipped manifest does not list.
     *
     * Protected prefixes (local state, dependencies, VCS data, mobile apps)
     * are never touched. Directories left empty by the sweep are removed in a
     * second walk, so a pruned subtree disappears entirely.
     */
    /**
     * The paths the cleanup sweep must never touch: the configured protected
     * prefixes, plus the live database files of every SQLite connection whose
     * file sits inside the installation. The database is user state, not
     * shipped code, so it can never appear in a release manifest; without this
     * a database kept outside `storage` (for example `database/database.sqlite`,
     * which the installer accepts) would be swept as stale on every update.
     * SQLite side files (`-wal`, `-shm`, `-journal`) ride along with their
     * database.
     */
    public static function protectedPaths(): array
    {
        $keep = config('invoiceshelf.update_protected_paths', []);

        foreach (config('database.connections', []) as $connection) {
            if (($connection['driver'] ?? null) !== 'sqlite') {
                continue;
            }

            $database = $connection['database'] ?? null;

            if (! is_string($database) || $database === '' || $database === ':memory:') {
                continue;
            }

            $root = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $absolute = str_starts_with($database, DIRECTORY_SEPARATOR) ? $database : $root.$database;

            if (! str_starts_with($absolute, $root)) {
                continue;
            }

            $relative = substr($absolute, strlen($root));

            foreach (['', '-wal', '-shm', '-journal'] as $suffix) {
                $keep[] = $relative.$suffix;
            }
        }

        return array_values(array_unique($keep));
    }

    public static function cleanStaleFiles(): array
    {
        $manifestPath = base_path('manifest.json');

        if (! File::exists($manifestPath)) {
            return ['success' => true, 'cleaned' => 0];
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest)) {
            return ['success' => false, 'error' => 'Invalid manifest'];
        }

        $shipped = array_flip($manifest);
        $keep = static::protectedPaths();
        $cleaned = 0;

        foreach (static::walkInstallation() as $entry) {
            $relative = static::relativeToInstallation($entry->getPathname());

            if (static::isKeptPath($relative, $keep)) {
                continue;
            }

            if ($entry->isFile() && ! isset($shipped[$relative])) {
                File::delete($entry->getPathname());
                $cleaned++;
            }
        }

        foreach (static::walkInstallation() as $entry) {
            if (! $entry->isDir()) {
                continue;
            }

            $relative = static::relativeToInstallation($entry->getPathname());

            if (static::isKeptPath($relative, $keep)) {
                continue;
            }

            if (static::hasNoEntries($entry->getPathname())) {
                @rmdir($entry->getPathname());
            }
        }

        return ['success' => true, 'cleaned' => $cleaned];
    }

    /**
     * Bring the schema up to date with the freshly copied code.
     *
     * The base-schema consolidation refuses databases whose 2.x history is
     * incomplete, or whose history and schema disagree. Asking it for that
     * verdict first turns such a database into a refusal carrying the
     * consolidation's own explanation, instead of an upgrade that copies the
     * new release over the installation and only then stops on a failed
     * migration.
     *
     * @throws \RuntimeException when the consolidation would refuse this database
     */
    public static function migrateUpdate()
    {
        $verdict = SchemaConsolidationGuard::preflight();

        if ($verdict?->isAbort()) {
            $verdict->fail();
        }

        Artisan::call('migrate --force');

        static::syncCurrencies();

        return true;
    }

    /**
     * Hand the release's currency list to the database.
     *
     * Currencies are reference data, and shipping them as migrations meant one
     * migration per currency for ever. They come from a catalogue now, and this
     * is what carries a new release's additions to an installation that already
     * exists. It is insert-if-absent, so running it on every upgrade costs one
     * query on an installation that is already current.
     *
     * A failure here is logged rather than thrown: the upgrade itself has
     * succeeded by this point, and refusing to finish it over a missing
     * currency would be worse than the missing currency. The admin area's
     * refresh button covers the gap.
     */
    private static function syncCurrencies(): void
    {
        try {
            Artisan::call('currencies:sync');
        } catch (\Throwable $e) {
            Log::warning('Currency sync after update failed: '.$e->getMessage());
        }
    }

    /**
     * Record the new version and announce the finished update.
     *
     * Cached configuration, routes and compiled services describe the release
     * that was replaced; an installation that ran `config:cache` would
     * otherwise keep serving, for example, an auth guard list without a guard
     * the new release added. They are cleared so the next request rebuilds
     * them from the new files.
     */
    public static function finishUpdate($installed, $version)
    {
        Setting::setSetting('version', $version);

        Artisan::call('optimize:clear');

        static::retireShippedKey();

        event(new UpdateFinished($installed, $version));

        return [
            'success' => true,
            'error' => false,
            'data' => [],
        ];
    }

    /**
     * Move an installation still on the APP_KEY that `.env.example` used to
     * ship onto a key of its own. It runs last because the new key signs
     * everyone out, the admin running this update included.
     *
     * A failure is logged rather than thrown, like the currency sync: the
     * update itself has succeeded, and the command can be run by hand.
     */
    private static function retireShippedKey(): void
    {
        try {
            if (Artisan::call('invoiceshelf:retire-shipped-key', ['--rotate' => true]) !== 0) {
                Log::critical('APP_KEY is still the public key InvoiceShelf used to ship: '.trim(Artisan::output()));
            }
        } catch (\Throwable $e) {
            Log::critical('Retiring the shipped APP_KEY after update failed: '.$e->getMessage());
        }
    }

    /**
     * Turn the release's requirement list into a name => satisfied map, with a
     * synthetic entry for the minimum interpreter version.
     */
    private static function gradeRequirements($required, $minimumPhpVersion): array
    {
        $graded = [];

        foreach ($required as $extension) {
            $graded[$extension] = phpversion($extension) !== false;
        }

        $graded[sprintf('php(%s)', $minimumPhpVersion)] = version_compare(phpversion(), $minimumPhpVersion, '>=');

        return $graded;
    }

    /**
     * Create an empty, randomly named working directory in private storage.
     */
    private static function makeTempDirectory(string $prefix): string
    {
        $directory = storage_path('app/'.$prefix.md5(mt_rand()));

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory);
        }

        return $directory;
    }

    /**
     * Depth-first walk over the whole installation, children before parents.
     */
    private static function walkInstallation(): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
    }

    /**
     * Does this directory hold nothing at all (dot entries aside)?
     */
    private static function hasNoEntries(string $directory): bool
    {
        return ! (new \FilesystemIterator($directory))->valid();
    }

    /**
     * Strip the installation root from an absolute path.
     */
    private static function relativeToInstallation(string $absolutePath): string
    {
        return substr($absolutePath, strlen(base_path()) + 1);
    }

    /**
     * Is this path itself protected, or does it live under a protected one?
     */
    private static function isKeptPath(string $relativePath, array $protectedPaths): bool
    {
        foreach ($protectedPaths as $protected) {
            if ($relativePath === $protected || str_starts_with($relativePath, $protected.'/')) {
                return true;
            }
        }

        return false;
    }
}
