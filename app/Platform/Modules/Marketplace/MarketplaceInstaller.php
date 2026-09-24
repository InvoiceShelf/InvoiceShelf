<?php

namespace App\Platform\Modules\Marketplace;

use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Events\ModuleInstalledEvent;
use App\Platform\Modules\Models\Module as InstalledModule;
use App\Platform\Modules\Runtime\ModuleRuntimeAutoloader;
use App\Platform\Operations\Models\Setting;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use RuntimeException;
use Throwable;
use ZipArchive;

class MarketplaceInstaller
{
    public function __construct(
        private MarketplaceClient $client,
        private MarketplaceOperationLease $operations,
    ) {}

    /**
     * @return array{success: bool, operation_id?: int, error?: string}
     */
    public function install(string $slug, string $version, string $channel): array
    {
        $operation = $this->operations->acquire($slug, $version, $channel);
        if ($operation === null) {
            return ['success' => false, 'error' => 'Another marketplace installation is in progress.'];
        }

        $workspace = null;

        try {
            $release = $this->release($slug, $version, $channel);
            $manifest = $release['manifest'];
            $moduleName = $this->moduleName($manifest);
            $this->assertManifestIdentity($manifest, $slug, $version, $moduleName);
            $this->assertCompatible($manifest, $channel);

            $installed = InstalledModule::query()->where('name', $moduleName)->first();
            if ($installed !== null && $installed->installed && version_compare($version, $installed->version, '<=')) {
                throw new RuntimeException('Downgrades and reinstalling the same release are not permitted.');
            }

            $workspace = $this->workspace((string) $operation->id);
            $zipPath = $workspace.'/artifact.zip';
            $this->downloadArtifact($release['artifact'], $zipPath);
            $this->validateArtifact($zipPath, $release['artifact']);

            $extracted = $workspace.'/extracted';
            $this->extractAndValidate($zipPath, $extracted, $slug, $version, $moduleName, $manifest);
            $this->assertSafeMigrations($extracted.'/'.$moduleName);

            $previous = $installed?->only(['version', 'installed', 'enabled', 'state', 'last_error', 'last_failed_at']);
            $backup = $this->swap($extracted.'/'.$moduleName, $moduleName, (string) $operation->id);

            try {
                ModuleRuntimeAutoloader::register($moduleName);
                $migrationPath = base_path("Modules/{$moduleName}/database/migrations");
                if (File::isDirectory($migrationPath)) {
                    $exitCode = Artisan::call('migrate', [
                        '--path' => $migrationPath,
                        '--realpath' => true,
                        '--force' => true,
                    ]);
                    if ($exitCode !== 0) {
                        throw new RuntimeException('Module migrations failed: '.trim(Artisan::output()));
                    }
                }

                $record = InstalledModule::query()->updateOrCreate(
                    ['name' => $moduleName],
                    [
                        'slug' => $slug,
                        'version' => $version,
                        'installed' => true,
                        'enabled' => true,
                        'state' => 'installed',
                        'last_error' => null,
                        'last_failed_at' => null,
                    ],
                );

                // The registry row is written first on purpose: the activator
                // reads it, so by the time the repository is registered the
                // module counts as enabled and its providers are registered and
                // booted. A ModuleEnabledEvent listener therefore sees whatever
                // the module declared to the SDK registry.
                Module::register();
                Module::find($moduleName)?->enable();
                Artisan::call('optimize:clear');
                Artisan::call('queue:restart');
                ModuleInstalledEvent::dispatch($record);
                ModuleEnabledEvent::dispatch($record);
                $this->operations->finish($operation, 'completed');
                $this->clean($workspace, $backup);

                return ['success' => true, 'operation_id' => $operation->id];
            } catch (Throwable $exception) {
                $this->restore($moduleName, $backup);
                $this->restoreDatabaseState($moduleName, $previous, $slug, $exception);
                throw $exception;
            }
        } catch (Throwable $exception) {
            report($exception);
            if (is_string($workspace) && File::isDirectory($workspace)) {
                File::deleteDirectory($workspace);
            }
            $this->operations->finish($operation, 'failed', $exception->getMessage());

            return ['success' => false, 'operation_id' => $operation->id, 'error' => $exception->getMessage()];
        }
    }

    /** @return array{manifest: array<string, mixed>, artifact: array<string, mixed>} */
    private function release(string $slug, string $version, string $channel): array
    {
        $response = $this->client->release($slug, $version, $channel);
        if (! $response->successful()) {
            throw new RuntimeException('Marketplace release request failed.');
        }

        $body = $response->json();
        if (! is_array($body) || array_diff(array_keys($body), ['success', 'manifest', 'signature', 'key_id', 'artifact', 'release_state', 'yanked_reason']) !== []
            || ($body['success'] ?? false) !== true || ! is_array($body['manifest'] ?? null)
            || ! is_array($body['artifact'] ?? null) || ! is_string($body['signature'] ?? null) || ! is_string($body['key_id'] ?? null)) {
            throw new RuntimeException('Marketplace returned an invalid release response.');
        }

        if (($body['release_state'] ?? null) !== 'published') {
            throw new RuntimeException('This release has been yanked and cannot be installed.');
        }

        $this->assertReleaseManifest($body['manifest'], $body['key_id']);
        $this->assertEnvelopeArtifact($body['artifact']);

        $signedArtifact = $body['manifest']['artifact'] ?? null;
        if (! is_array($signedArtifact)
            || ($signedArtifact['sha256'] ?? null) !== ($body['artifact']['sha256'] ?? null)
            || ($signedArtifact['bytes'] ?? null) !== ($body['artifact']['bytes'] ?? null)) {
            throw new RuntimeException('Release artifact integrity fields are not covered by the signed manifest.');
        }

        $this->verifySignature($body['manifest'], $body['signature'], $body['key_id'] ?? null);

        return ['manifest' => $body['manifest'], 'artifact' => $body['artifact']];
    }

    private function verifySignature(array $manifest, string $signature, mixed $keyId): void
    {
        $signatureBytes = base64_decode($signature, true);
        if ($signatureBytes === false || strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! is_string($keyId) || ! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('The release signature cannot be verified.');
        }

        $keys = config('invoiceshelf.marketplace.public_keys', []);
        $keys = is_array($keys) ? $keys : [];
        if (! isset($keys[$keyId]) || ! is_string($keys[$keyId])) {
            throw new RuntimeException('The release uses an unknown signing key.');
        }

        $payload = CanonicalJson::encode($manifest);
        $publicKey = base64_decode($keys[$keyId], true);
        if ($publicKey !== false && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && sodium_crypto_sign_verify_detached($signatureBytes, $payload, $publicKey)) {
            return;
        }

        throw new RuntimeException('The release signature is invalid or was signed by an unpinned key.');
    }

    private function assertCompatible(array $manifest, string $channel): void
    {
        if (($manifest['channel'] ?? null) !== $channel) {
            throw new RuntimeException('Release channel does not match the requested channel.');
        }

        $compatibility = $manifest['compatibility'] ?? [];
        if (! is_array($compatibility)) {
            throw new RuntimeException('Release compatibility metadata is invalid.');
        }

        $appVersion = (string) config('app.version', Setting::getSetting('version'));
        $minimum = $compatibility['invoiceshelf'] ?? null;
        if (is_string($minimum) && $minimum !== '' && ! $this->satisfiesConstraint($appVersion, $minimum)) {
            throw new RuntimeException('This module requires a newer InvoiceShelf version.');
        }

        $php = $compatibility['php'] ?? null;
        if (is_string($php) && $php !== '' && ! $this->satisfiesConstraint(PHP_VERSION, $php)) {
            throw new RuntimeException('This module requires a newer PHP version.');
        }

        $moduleApi = $compatibility['module_api'] ?? null;
        if (! is_string($moduleApi) || ! $this->satisfiesConstraint((string) config('invoiceshelf.marketplace.module_api_version'), $moduleApi)) {
            throw new RuntimeException('This module requires an unsupported module runtime API.');
        }

        foreach (($compatibility['extensions'] ?? []) as $extension) {
            if (! is_string($extension) || ! str_starts_with($extension, 'ext-') || ! extension_loaded(substr($extension, 4))) {
                throw new RuntimeException('A required PHP extension is unavailable.');
            }
        }
    }

    private function downloadArtifact(array $artifact, string $path): void
    {
        $url = $artifact['download_url'] ?? null;
        if (! is_string($url)) {
            throw new RuntimeException('Marketplace returned an unsafe artifact URL.');
        }
        if (! is_int($artifact['bytes'] ?? null) || $artifact['bytes'] > config('invoiceshelf.marketplace.max_zip_compressed_bytes')) {
            throw new RuntimeException('Module artifact exceeds the configured download limit.');
        }

        $response = $this->client->artifact($url, $path);
        if (! $response->successful()) {
            throw new RuntimeException('Module artifact download failed.');
        }
    }

    private function validateArtifact(string $path, array $artifact): void
    {
        $expectedHash = $artifact['sha256'] ?? null;
        $expectedBytes = $artifact['bytes'] ?? null;
        if (! is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
            || ! is_int($expectedBytes) || $expectedBytes < 1
            || hash_file('sha256', $path) !== $expectedHash || filesize($path) !== $expectedBytes) {
            throw new RuntimeException('Module artifact integrity validation failed.');
        }
    }

    private function assertManifestIdentity(array $manifest, string $slug, string $version, string $moduleName): void
    {
        if (($manifest['slug'] ?? null) !== $slug || ($manifest['module_name'] ?? null) !== $moduleName || ($manifest['version'] ?? null) !== $version) {
            throw new RuntimeException('Release manifest does not match the requested module release.');
        }
    }

    private function assertReleaseManifest(array $manifest, string $keyId): void
    {
        $expected = ['schema_version', 'slug', 'module_name', 'version', 'channel', 'publication', 'compatibility', 'artifact', 'key_id', 'source_commit', 'released_at'];
        if (array_diff(array_keys($manifest), $expected) !== [] || array_diff($expected, array_keys($manifest)) !== []
            || ($manifest['schema_version'] ?? null) !== 1 || ($manifest['publication'] ?? null) !== 'published'
            || ! is_string($manifest['slug'] ?? null) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $manifest['slug']) !== 1
            || ! is_string($manifest['module_name'] ?? null) || preg_match('/^[A-Z][A-Za-z0-9]*$/', $manifest['module_name']) !== 1
            || ! is_string($manifest['version'] ?? null) || ! $this->isSemverVersion($manifest['version'])
            || ! is_string($manifest['channel'] ?? null) || ! in_array($manifest['channel'], ['stable', 'insider'], true)
            || ! is_string($manifest['key_id'] ?? null) || $manifest['key_id'] !== $keyId
            || ! is_string($manifest['source_commit'] ?? null) || preg_match('/^[0-9a-f]{40}$/', $manifest['source_commit']) !== 1
            || ! is_string($manifest['released_at'] ?? null) || strtotime($manifest['released_at']) === false) {
            throw new RuntimeException('Signed release manifest has an invalid schema.');
        }

        if (($manifest['channel'] === 'stable' && str_contains($manifest['version'], '-'))
            || ($manifest['channel'] === 'insider' && ! str_contains($manifest['version'], '-'))) {
            throw new RuntimeException('Signed release manifest has an invalid channel/version combination.');
        }

        $compatibility = $manifest['compatibility'] ?? null;
        if (! is_array($compatibility) || array_diff(array_keys($compatibility), ['invoiceshelf', 'module_api', 'php', 'extensions']) !== []
            || array_diff(['invoiceshelf', 'module_api', 'php', 'extensions'], array_keys($compatibility)) !== []
            || ! is_array($compatibility['extensions']) || ! array_is_list($compatibility['extensions'])) {
            throw new RuntimeException('Signed release compatibility metadata has an invalid schema.');
        }
        foreach (['invoiceshelf', 'module_api', 'php'] as $field) {
            if (! is_string($compatibility[$field]) || ! $this->isSemverConstraint($compatibility[$field])) {
                throw new RuntimeException('Signed release compatibility constraint is invalid.');
            }
        }
        foreach ($compatibility['extensions'] as $extension) {
            if (! is_string($extension) || preg_match('/^ext-[a-z0-9][a-z0-9_-]*$/', $extension) !== 1) {
                throw new RuntimeException('Signed release extension requirement is invalid.');
            }
            if (count(array_keys($compatibility['extensions'], $extension, true)) > 1) {
                throw new RuntimeException('Signed release extension requirements must not be duplicated.');
            }
        }
        $this->assertReleaseArtifact($manifest['artifact']);
    }

    private function assertReleaseArtifact(mixed $artifact): void
    {
        if (! is_array($artifact) || array_diff(array_keys($artifact), ['sha256', 'bytes']) !== []
            || array_diff(['sha256', 'bytes'], array_keys($artifact)) !== []
            || ! is_string($artifact['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $artifact['sha256']) !== 1
            || ! is_int($artifact['bytes'] ?? null) || $artifact['bytes'] < 1) {
            throw new RuntimeException('Release artifact metadata has an invalid schema.');
        }
    }

    private function assertEnvelopeArtifact(array $artifact): void
    {
        if (array_diff(array_keys($artifact), ['sha256', 'bytes', 'download_url', 'expires_at']) !== []
            || array_diff(['sha256', 'bytes', 'download_url', 'expires_at'], array_keys($artifact)) !== []
            || ! is_string($artifact['download_url'] ?? null) || filter_var($artifact['download_url'], FILTER_VALIDATE_URL) === false
            || ! is_string($artifact['expires_at'] ?? null) || strtotime($artifact['expires_at']) === false
            || strtotime($artifact['expires_at']) <= now()->getTimestamp()) {
            throw new RuntimeException('Release download envelope has an invalid artifact.');
        }

        $this->assertReleaseArtifact([
            'sha256' => $artifact['sha256'] ?? null,
            'bytes' => $artifact['bytes'] ?? null,
        ]);
    }

    private function satisfiesConstraint(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies(ltrim($version, 'v'), $constraint);
        } catch (Throwable) {
            return false;
        }
    }

    private function isSemverVersion(string $version): bool
    {
        return preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', $version) === 1;
    }

    private function isSemverConstraint(string $constraint): bool
    {
        if ($constraint === '' || str_contains($constraint, '||') || str_contains($constraint, '*')) {
            return false;
        }

        $version = '(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:\.(?:0|[1-9]\d*))?(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?';
        $part = '(?:\^|~|>=|<=|>|<|=)?v?'.$version;

        return preg_match('/^'.$part.'(?:\s+'.$part.')*$/', $constraint) === 1;
    }

    private function extractAndValidate(string $zipPath, string $destination, string $slug, string $version, string $moduleName, array $manifest): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Module artifact is not a valid ZIP archive.');
        }

        try {
            $limits = config('invoiceshelf.marketplace');
            if ($zip->numFiles > $limits['max_zip_entries']) {
                throw new RuntimeException('Module archive contains too many files.');
            }

            $entries = [];
            $root = null;
            $compressed = 0;
            $uncompressed = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = is_array($stat) ? ($stat['name'] ?? null) : null;
                if (! is_string($name) || ! $this->safeZipPath($name)) {
                    throw new RuntimeException('Module archive contains an unsafe path.');
                }
                $normal = rtrim($name, '/');
                if ($normal !== '' && isset($entries[$normal])) {
                    throw new RuntimeException('Module archive contains duplicate paths.');
                }
                $entries[$normal] = $name;
                $parts = explode('/', $normal);
                $root ??= $parts[0] ?? null;
                if (($parts[0] ?? null) !== $root || $this->zipEntryIsSymlink($zip, $index)) {
                    throw new RuntimeException('Module archive has an invalid root or symbolic link.');
                }
                $compressed += (int) ($stat['comp_size'] ?? 0);
                $uncompressed += (int) ($stat['size'] ?? 0);
            }

            if ($root !== $moduleName || $compressed > $limits['max_zip_compressed_bytes'] || $uncompressed > $limits['max_zip_uncompressed_bytes']
                || ($compressed > 0 && $uncompressed / $compressed > $limits['max_zip_compression_ratio'])) {
                throw new RuntimeException('Module archive exceeds limits or has an unexpected root directory.');
            }

            foreach ($entries as $normal => $name) {
                if ($name === '') {
                    continue;
                }
                $target = $destination.'/'.$normal;
                if (str_ends_with($name, '/')) {
                    File::makeDirectory($target, 0755, true, true);

                    continue;
                }
                File::ensureDirectoryExists(dirname($target));
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('Module archive entry cannot be read.');
                }
                file_put_contents($target, stream_get_contents($stream));
                fclose($stream);
            }
        } finally {
            $zip->close();
        }

        $moduleJson = $destination.'/'.$moduleName.'/module.json';
        $metadata = json_decode((string) File::get($moduleJson), true);
        if (! is_array($metadata) || ! in_array($metadata['schema_version'] ?? null, [1, 2], true) || ($metadata['name'] ?? null) !== $moduleName || ! is_array($metadata['providers'] ?? null)
            || ($manifest['slug'] ?? null) !== $slug || ($manifest['module_name'] ?? null) !== $moduleName || ($manifest['version'] ?? null) !== $version) {
            throw new RuntimeException('Module metadata does not match the signed release manifest.');
        }

        if (($metadata['slug'] ?? null) !== $slug || ($metadata['version'] ?? null) !== $version
            || ($metadata['compatibility'] ?? null) !== ($manifest['compatibility'] ?? null)) {
            throw new RuntimeException('Module package metadata does not match signed release compatibility.');
        }

        $this->assertModuleManifest($metadata, $moduleName, $slug);
        if (($metadata['schema_version'] ?? null) === 2) {
            $this->assertSchemaV2WithSdk($destination.'/'.$moduleName);
        }

        foreach ($metadata['providers'] as $provider) {
            if (! is_string($provider) || ! str_starts_with($provider, "Modules\\{$moduleName}\\")
                || preg_match('/^Modules\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)*$/', $provider) !== 1
                || ! File::isFile($destination.'/'.$moduleName.'/app/'.str_replace('\\', '/', substr($provider, strlen("Modules\\{$moduleName}\\"))).'.php')) {
                throw new RuntimeException('Module has an invalid service provider declaration.');
            }
        }

        $this->assertDependencyPolicy($destination.'/'.$moduleName, $metadata);
        $this->assertAssetPolicy($destination.'/'.$moduleName, $metadata);
    }

    private function assertDependencyPolicy(string $modulePath, array $metadata): void
    {
        if (($metadata['dependency_policy'] ?? null) !== 'host-provided-only' || ! is_array($metadata['module_dependencies'] ?? null)) {
            throw new RuntimeException('Module dependency policy is invalid.');
        }
        foreach ($metadata['module_dependencies'] as $dependencySlug => $constraint) {
            $dependency = is_string($dependencySlug)
                ? InstalledModule::query()->where('slug', $dependencySlug)->where('enabled', true)->first()
                : null;
            if (! is_string($constraint) || $dependency === null || ! $this->satisfiesConstraint($dependency->version, $constraint)) {
                throw new RuntimeException('A required module dependency is not enabled.');
            }
        }

        $composer = $modulePath.'/composer.json';
        if (! File::exists($composer)) {
            throw new RuntimeException('Module archive does not contain composer.json.');
        }
        $data = json_decode((string) File::get($composer), true);
        if (! is_array($data) || array_is_list($data)
            || ($data['name'] ?? null) !== "invoiceshelf/module-{$metadata['slug']}"
            || ($data['license'] ?? null) !== 'AGPL-3.0-only'
            || ! is_array($data['require'] ?? null) || array_is_list($data['require'])) {
            throw new RuntimeException('Module composer manifest is invalid.');
        }
        foreach ($data['require'] as $package => $constraint) {
            if (! is_string($package) || ! is_string($constraint)
                || ($package !== 'php' && $package !== 'invoiceshelf/modules' && ! str_starts_with($package, 'ext-'))
                || (in_array($package, ['php', 'invoiceshelf/modules'], true) && ! $this->isSemverConstraint($constraint))
                || (str_starts_with($package, 'ext-') && $constraint !== '*' && ! $this->isSemverConstraint($constraint))) {
                throw new RuntimeException('Module packages may only depend on host-provided dependencies.');
            }
        }
    }

    private function assertModuleManifest(array $metadata, string $moduleName, string $slug): void
    {
        $expected = ['name', 'alias', 'description', 'keywords', 'priority', 'providers', 'aliases', 'files', 'requires', 'schema_version', 'slug', 'version', 'license', 'compatibility', 'module_dependencies', 'migration_policy', 'dependency_policy', 'assets'];
        if (($metadata['schema_version'] ?? null) === 2) {
            $expected[] = 'uninstall';
        }

        if (array_diff(array_keys($metadata), $expected) !== [] || array_diff($expected, array_keys($metadata)) !== []
            || ! is_string($metadata['alias'] ?? null) || preg_match('/^[a-z][a-z0-9_]*$/', $metadata['alias']) !== 1
            || ! is_string($metadata['description'] ?? null) || ! is_int($metadata['priority'] ?? null) || $metadata['priority'] < 0
            || ($metadata['license'] ?? null) !== 'AGPL-3.0-only'
            || ! is_array($metadata['keywords'] ?? null) || ! array_is_list($metadata['keywords'])
            || ! is_array($metadata['providers'] ?? null) || ! array_is_list($metadata['providers']) || $metadata['providers'] === []
            || ! is_array($metadata['aliases'] ?? null) || (! empty($metadata['aliases']) && array_is_list($metadata['aliases']))
            || ! is_array($metadata['requires'] ?? null) || (! empty($metadata['requires']) && array_is_list($metadata['requires']))
            || ! is_array($metadata['files'] ?? null) || ! array_is_list($metadata['files'])
            || ! is_array($metadata['assets'] ?? null) || ! array_is_list($metadata['assets'])
            || ! in_array($metadata['schema_version'] ?? null, [1, 2], true)
            || ($metadata['migration_policy'] ?? null) !== (($metadata['schema_version'] ?? null) === 2 ? 'reversible' : 'forward-only')
            || ($metadata['dependency_policy'] ?? null) !== 'host-provided-only') {
            throw new RuntimeException('Module package manifest has an invalid schema.');
        }

        if (($metadata['schema_version'] ?? null) === 2) {
            $uninstall = $metadata['uninstall'] ?? null;
            $cleanup = is_array($uninstall) ? ($uninstall['data_cleanup'] ?? null) : null;
            if (! is_array($uninstall) || array_keys($uninstall) !== ['data_cleanup'] || ! is_string($cleanup)
                || preg_match('/^Modules\\\\'.preg_quote($moduleName, '/').'\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)*$/', $cleanup) !== 1) {
                throw new RuntimeException('Module uninstall metadata has an invalid schema.');
            }
        }

        foreach ($metadata['keywords'] as $keyword) {
            if (! is_string($keyword)) {
                throw new RuntimeException('Module package keywords must be strings.');
            }
        }
        if (count($metadata['providers']) !== count(array_unique($metadata['providers']))) {
            throw new RuntimeException('Module package providers must not be duplicated.');
        }
        foreach ($metadata['files'] as $file) {
            if (! is_string($file) || preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $file) !== 1
                || str_contains($file, '..') || str_contains($file, '//')) {
                throw new RuntimeException('Module loader files must be local paths.');
            }
        }
        foreach ($metadata['module_dependencies'] as $dependencySlug => $constraint) {
            if (! is_string($dependencySlug) || $dependencySlug === $slug
                || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $dependencySlug) !== 1
                || ! is_string($constraint) || ! $this->isSemverConstraint($constraint)) {
                throw new RuntimeException('Module dependency declaration is invalid.');
            }
        }
        if (count($metadata['assets']) !== count(array_unique($metadata['assets']))) {
            throw new RuntimeException('Module package assets must not be duplicated.');
        }
    }

    private function assertSafeMigrations(string $modulePath): void
    {
        $metadata = json_decode((string) File::get($modulePath.'/module.json'), true);
        if (($metadata['schema_version'] ?? null) === 2 && ($metadata['migration_policy'] ?? null) === 'reversible') {
            return;
        }

        if (($metadata['schema_version'] ?? null) !== 1 || ($metadata['migration_policy'] ?? null) !== 'forward-only') {
            throw new RuntimeException('Module migrations must declare the forward-only policy.');
        }
        foreach (File::glob($modulePath.'/database/migrations/*.php') as $migration) {
            $source = (string) File::get($migration);
            if (preg_match('/\b(drop|rename|truncate|delete|update)\w*\s*\(/i', $source) === 1) {
                throw new RuntimeException('Module migrations must be forward-only additive migrations.');
            }
        }
    }

    private function assertSchemaV2WithSdk(string $modulePath): void
    {
        $validator = 'InvoiceShelf\\Modules\\Manifest\\ManifestValidator';
        if (! class_exists($validator) || ! method_exists($validator, 'package')) {
            throw new RuntimeException('Schema-v2 modules require invoiceshelf/modules ^3.2.');
        }

        try {
            $validator::package($modulePath);
        } catch (Throwable $exception) {
            throw new RuntimeException('Module schema-v2 validation failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function safeZipPath(string $name): bool
    {
        return $name !== '' && ! str_contains($name, "\0") && ! str_starts_with($name, '/') && ! preg_match('/^[A-Za-z]:/', $name)
            && ! str_contains($name, '\\') && ! array_intersect(['.', '..', ''], explode('/', $name)) && ! str_contains($name, '//');
    }

    private function zipEntryIsSymlink(ZipArchive $zip, int $index): bool
    {
        $result = $zip->getExternalAttributesIndex($index, $opsys, $attributes);

        return $result && (($attributes >> 16) & 0170000) === 0120000;
    }

    private function assertAssetPolicy(string $modulePath, array $metadata): void
    {
        foreach (($metadata['assets'] ?? []) as $asset) {
            if (! is_string($asset) || preg_match('#^dist/[A-Za-z0-9][A-Za-z0-9._/-]*\.(?:js|css)$#', $asset) !== 1
                || str_contains($asset, '..') || str_contains($asset, '//') || ! File::isFile($modulePath.'/'.$asset)) {
                throw new RuntimeException('Module assets must be local, path-contained files.');
            }
        }
    }

    private function moduleName(array $manifest): string
    {
        $name = $manifest['module_name'] ?? null;
        if (! is_string($name) || preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            throw new RuntimeException('Release manifest has an invalid module name.');
        }

        return $name;
    }

    private function workspace(string $id): string
    {
        $path = base_path("Modules/.staging/{$id}");
        File::ensureDirectoryExists($path);

        return $path;
    }

    private function swap(string $source, string $name, string $operation): ?string
    {
        $target = base_path("Modules/{$name}");
        $backup = base_path("Modules/.backups/{$name}-{$operation}");
        File::ensureDirectoryExists(dirname($backup));
        if (File::exists($target) && ! rename($target, $backup)) {
            throw new RuntimeException('Could not back up the installed module.');
        }
        if (! rename($source, $target)) {
            if (File::exists($backup)) {
                rename($backup, $target);
            }
            throw new RuntimeException('Could not activate the staged module.');
        }

        return File::exists($backup) ? $backup : null;
    }

    private function restore(string $name, ?string $backup): void
    {
        $target = base_path("Modules/{$name}");
        if ($backup !== null && File::exists($backup)) {
            File::deleteDirectory($target);
            rename($backup, $target);

            return;
        }

        File::deleteDirectory($target);
    }

    private function restoreDatabaseState(string $name, ?array $previous, string $slug, Throwable $exception): void
    {
        if ($previous === null) {
            InstalledModule::query()->updateOrCreate(['name' => $name], [
                'slug' => $slug, 'version' => '0.0.0', 'installed' => false, 'enabled' => false, 'state' => 'failed',
                'last_error' => Str::limit($exception->getMessage(), 65000), 'last_failed_at' => now(),
            ]);

            return;
        }
        InstalledModule::query()->where('name', $name)->update([
            ...$previous,
            'state' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 65000), 'last_failed_at' => now(),
        ]);
    }

    private function clean(string $workspace, ?string $backup): void
    {
        File::deleteDirectory($workspace);
        if ($backup !== null) {
            File::deleteDirectory($backup);
        }
        foreach (File::directories(base_path('Modules/.staging')) as $directory) {
            if (filemtime($directory) < now()->subDay()->getTimestamp()) {
                File::deleteDirectory($directory);
            }
        }
    }
}
