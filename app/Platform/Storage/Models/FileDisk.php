<?php

namespace App\Platform\Storage\Models;

use App\Platform\Storage\Application\FileDiskService;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A named storage target: a driver plus the credential blob that configures it.
 */
class FileDisk extends Model
{
    use HasFactory;

    public const DISK_TYPE_SYSTEM = 'SYSTEM';

    public const DISK_TYPE_REMOTE = 'REMOTE';

    /**
     * The drivers a disk may be registered with. Every other entry in
     * config/filesystems.php is an internal disk rooted somewhere in the
     * application tree and must never be reachable through a disk row.
     */
    public const DRIVERS = ['local', 's3', 's3compat', 'doSpaces', 'dropbox'];

    protected $table = 'file_disks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['set_as_default' => 'boolean'];
    }

    public function setCredentialsAttribute(mixed $value): void
    {
        $this->attributes['credentials'] = json_encode($value);
    }

    /**
     * Read the credential blob back as a collection.
     *
     * Rows written by older releases hold a JSON string *inside* the JSON
     * column, so a first decode that yields a string is decoded once more.
     */
    public function getDecodedCredentials(): Collection
    {
        $payload = json_decode($this->credentials, true);

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        return collect($payload ?? []);
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    public function scopeFileDisksBetween($query, $start, $end)
    {
        return $query->whereBetween('file_disks.created_at', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $keyword) {
            $like = '%'.$keyword.'%';

            $query->where('name', 'LIKE', $like)->orWhere('driver', 'LIKE', $like);
        }
    }

    public function scopePaginateData($query, $limit)
    {
        return $limit == 'all'
            ? $query->get()
            : $query->paginate($limit);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $criteria = collect($filters);

        if ($criteria->get('search')) {
            $query->whereSearch($criteria->get('search'));
        }

        if ($criteria->get('from_date') && $criteria->get('to_date')) {
            $query->fileDisksBetween(
                Carbon::createFromFormat('Y-m-d', $criteria->get('from_date')),
                Carbon::createFromFormat('Y-m-d', $criteria->get('to_date'))
            );
        }

        if ($criteria->get('orderByField') || $criteria->get('orderBy')) {
            $query->whereOrder(
                $criteria->get('orderByField') ?: 'sequence_number',
                $criteria->get('orderBy') ?: 'asc'
            );
        }
    }

    /**
     * Register this disk and point the runtime filesystem default at it.
     *
     * @deprecated Reach for FileDiskService::registerDisk(); this variant also
     * rewrites filesystems.default, which leaks into unrelated storage calls.
     */
    public function setConfig(): void
    {
        $registered = app(FileDiskService::class)->registerDisk($this);

        config(['filesystems.default' => $registered]);
    }

    /**
     * Whether this row is flagged as the installation-wide default disk.
     */
    public function setAsDefault(): bool
    {
        return $this->set_as_default;
    }

    /**
     * Publish a throwaway disk built from the driver's base config overlaid
     * with the supplied credentials, and select it as the runtime default.
     *
     * @deprecated Register a persisted row through FileDiskService instead.
     */
    public static function setFilesystem(Collection $credentials, string $driver): void
    {
        $target = env('DYNAMIC_DISK_PREFIX', 'temp_').$driver;

        config(['filesystems.default' => $target]);

        $settings = config('filesystems.disks.'.$driver);

        foreach ($settings as $field => $current) {
            if ($credentials->has($field)) {
                $settings[$field] = $credentials[$field];
            }
        }

        if ($driver === 'local' && isset($settings['root']) && ! str_starts_with($settings['root'], '/')) {
            $settings['root'] = storage_path('app/'.$settings['root']);
        }

        config(['filesystems.disks.'.$target => $settings]);
    }

    public function isSystem(): bool
    {
        return $this->hasType(self::DISK_TYPE_SYSTEM);
    }

    public function isRemote(): bool
    {
        return $this->hasType(self::DISK_TYPE_REMOTE);
    }

    private function hasType(string $expected): bool
    {
        return $this->type === $expected;
    }
}
