<?php

namespace App\Platform\Modules\Marketplace;

use App\Platform\Modules\Contracts\ModuleSettingsStore;
use App\Platform\Modules\Events\ModuleUninstalledEvent;
use App\Platform\Modules\Models\Module as InstalledModule;
use App\Platform\Modules\Runtime\OpcacheReset;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use RuntimeException;
use Throwable;

class MarketplaceUninstaller
{
    public function __construct(
        private MarketplaceOperationLease $operations,
        private ModuleSettingsStore $settings,
    ) {}

    /**
     * @return array{success: bool, operation_id?: int, error?: string}
     */
    public function uninstall(string $name, bool $removeData, ?string $confirmation = null): array
    {
        $module = InstalledModule::query()->where('name', $name)->where('installed', true)->first();
        if ($module === null) {
            return $this->failure('module_not_installed');
        }

        if ($removeData && $confirmation !== $module->name) {
            return $this->failure('module_confirmation_mismatch');
        }

        $operation = $this->operations->acquire($module->slug, $module->version, 'uninstall');
        if ($operation === null) {
            return $this->failure('operation_in_progress');
        }

        $backup = null;
        $mutationStarted = false;

        try {
            if ($dependents = $this->installedDependents($module)) {
                throw new ModuleUninstallException('dependent_modules_installed', implode(', ', $dependents));
            }

            $runtime = Module::find($module->name);
            if ($runtime === null) {
                if ($removeData) {
                    $mutationStarted = true;
                    throw new ModuleUninstallException('module_runtime_missing');
                }

                $mutationStarted = true;
                $this->removeCodeOnly($module);
                $this->operations->finish($operation, 'completed');
                ModuleUninstalledEvent::dispatch($module);

                return ['success' => true, 'operation_id' => $operation->id];
            }

            $manifest = $removeData ? $this->cleanupManifest($module) : null;
            $cleanup = $removeData ? $this->resolveDataCleanup($manifest['data_cleanup']) : null;
            $mutationStarted = true;
            $runtime->disable();
            $module->refresh();

            if ($removeData) {
                $this->runDataCleanup($cleanup);
                $this->resetMigrations($module);
                $this->settings->deleteForModule($manifest['slug']);
            }

            $backup = $this->backUpRuntime($module, (string) $operation->id);
            $this->markUninstalled($module);
            $this->refreshRuntimeCaches();
            ModuleUninstalledEvent::dispatch($module);
            $this->operations->finish($operation, 'completed');
            $this->removeBackupBestEffort($backup);

            return ['success' => true, 'operation_id' => $operation->id];
        } catch (Throwable $exception) {
            if (! $exception instanceof ModuleUninstallException || $exception->errorCode === 'uninstall_failed') {
                report($exception);
            }
            if ($mutationStarted) {
                $this->restoreRuntime($module, $backup);
            }
            $error = $exception instanceof ModuleUninstallException
                ? $exception->errorCode
                : 'uninstall_failed';
            if ($mutationStarted) {
                $this->markFailed($module, $error, $exception);
            }
            $this->operations->finish($operation, 'failed', $error);

            return $this->failure($error, $operation->id);
        }
    }

    /** @return list<string> */
    private function installedDependents(InstalledModule $module): array
    {
        $slug = $module->slug ?? $this->moduleSlug($module->name);
        if ($slug === null) {
            return [];
        }

        return InstalledModule::query()->where('installed', true)->where('name', '!=', $module->name)
            ->get()
            ->filter(function (InstalledModule $candidate) use ($slug): bool {
                $metadata = $this->metadata($candidate->name);

                return is_array($metadata)
                    && array_key_exists($slug, $metadata['module_dependencies'] ?? []);
            })
            ->pluck('name')
            ->values()
            ->all();
    }

    /** @return array{data_cleanup: string, slug: string} */
    private function cleanupManifest(InstalledModule $module): array
    {
        $metadata = $this->metadata($module->name);
        $uninstall = is_array($metadata) ? ($metadata['uninstall'] ?? null) : null;
        $cleanup = is_array($uninstall) ? ($uninstall['data_cleanup'] ?? null) : null;
        $slug = is_array($metadata) ? ($metadata['slug'] ?? null) : null;

        if (($metadata['schema_version'] ?? null) !== 2
            || ($metadata['migration_policy'] ?? null) !== 'reversible'
            || ! is_array($uninstall)
            || array_keys($uninstall) !== ['data_cleanup']
            || ! is_string($cleanup)
            || ! is_string($slug)
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || preg_match('/^Modules\\\\'.preg_quote($module->name, '/').'\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)*$/', $cleanup) !== 1) {
            throw new ModuleUninstallException('cleanup_not_supported');
        }

        return ['data_cleanup' => $cleanup, 'slug' => $slug];
    }

    private function resetMigrations(InstalledModule $module): void
    {
        $exitCode = Artisan::call('module:migrate-reset', [
            'module' => $module->name,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new ModuleUninstallException('uninstall_failed', trim(Artisan::output()));
        }
    }

    private function resolveDataCleanup(string $cleanupClass): object
    {
        $contract = 'InvoiceShelf\\Modules\\Contracts\\DataCleanup';
        if (! interface_exists($contract)) {
            throw new ModuleUninstallException('cleanup_not_supported');
        }

        try {
            $parameters = is_subclass_of($cleanupClass, ServiceProvider::class)
                ? ['app' => app()]
                : [];
            $cleanup = app()->make($cleanupClass, $parameters);
            if (! is_a($cleanup, $contract)) {
                throw new ModuleUninstallException('cleanup_not_supported');
            }
        } catch (ModuleUninstallException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ModuleUninstallException('cleanup_not_supported', $exception->getMessage());
        }

        return $cleanup;
    }

    private function runDataCleanup(object $cleanup): void
    {
        try {
            $cleanup->cleanup();
        } catch (Throwable $exception) {
            throw new ModuleUninstallException('uninstall_failed', $exception->getMessage());
        }
    }

    private function removeCodeOnly(InstalledModule $module): void
    {
        $runtime = base_path('Modules/'.$module->name);
        if (File::isDirectory($runtime) && ! File::deleteDirectory($runtime)) {
            throw new RuntimeException('Could not remove module runtime files.');
        }
        $this->markUninstalled($module);
        $this->refreshRuntimeCaches();
    }

    private function backUpRuntime(InstalledModule $module, string $operationId): ?string
    {
        $runtime = base_path('Modules/'.$module->name);
        if (! File::isDirectory($runtime)) {
            throw new ModuleUninstallException('module_runtime_missing');
        }

        $backup = base_path('Modules/.backups/uninstall-'.$operationId.'-'.$module->name);
        File::ensureDirectoryExists(dirname($backup));
        if (! rename($runtime, $backup)) {
            throw new RuntimeException('Could not move module runtime to the uninstall backup.');
        }

        return $backup;
    }

    private function restoreRuntime(InstalledModule $module, ?string $backup): void
    {
        if ($backup === null || ! File::isDirectory($backup)) {
            return;
        }

        $runtime = base_path('Modules/'.$module->name);
        File::deleteDirectory($runtime);
        rename($backup, $runtime);
    }

    private function removeBackupBestEffort(?string $backup): void
    {
        if ($backup !== null && File::isDirectory($backup) && ! File::deleteDirectory($backup)) {
            report(new RuntimeException('Could not remove module uninstall backup.'));
        }
    }

    private function markUninstalled(InstalledModule $module): void
    {
        $module->update([
            'installed' => false,
            'enabled' => false,
            'state' => 'uninstalled',
            'last_error' => null,
            'last_failed_at' => null,
        ]);
    }

    private function refreshRuntimeCaches(): void
    {
        if (Artisan::call('optimize:clear') !== 0
            || Artisan::call('queue:restart') !== 0) {
            throw new RuntimeException('Could not refresh module runtime caches.');
        }
        OpcacheReset::afterCodeChange();
    }

    private function markFailed(InstalledModule $module, string $error, Throwable $exception): void
    {
        $module->update([
            'installed' => true,
            'enabled' => false,
            'state' => 'failed',
            'last_error' => Str::limit($error.': '.$exception->getMessage(), 65000),
            'last_failed_at' => now(),
        ]);
    }

    private function moduleSlug(string $name): ?string
    {
        $metadata = $this->metadata($name);

        return is_array($metadata) && is_string($metadata['slug'] ?? null) ? $metadata['slug'] : null;
    }

    /** @return ?array<string, mixed> */
    private function metadata(string $name): ?array
    {
        $path = base_path('Modules/'.$name.'/module.json');
        if (! File::isFile($path)) {
            return null;
        }

        $metadata = json_decode((string) File::get($path), true);

        return is_array($metadata) ? $metadata : null;
    }

    /** @return array{success: false, error: string, operation_id?: int} */
    private function failure(string $error, ?int $operationId = null): array
    {
        return array_filter([
            'success' => false,
            'error' => $error,
            'operation_id' => $operationId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

class ModuleUninstallException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message ?: $errorCode);
    }
}
