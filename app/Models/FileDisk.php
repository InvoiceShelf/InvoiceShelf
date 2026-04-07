<?php

namespace App\Models;

use App\Services\FileDiskService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FileDisk extends Model
{
    use HasFactory;

    public const DISK_TYPE_SYSTEM = 'SYSTEM';

    public const DISK_TYPE_REMOTE = 'REMOTE';

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'set_as_default' => 'boolean',
        ];
    }

    public function setCredentialsAttribute(mixed $value): void
    {
        $this->attributes['credentials'] = json_encode($value);
    }

    /**
     * Decode credentials, handling double-encoded JSON from legacy data.
     */
    public function getDecodedCredentials(): Collection
    {
        $decoded = json_decode($this->credentials, true);

        // Handle double-encoded JSON (string inside string)
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return collect($decoded ?? []);
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeFileDisksBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'file_disks.created_at',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->where('name', 'LIKE', '%'.$term.'%')
                ->orWhere('driver', 'LIKE', '%'.$term.'%');
        }
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);
        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->fileDisksBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'sequence_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    /**
     * Apply this disk's credentials to the filesystem configuration at runtime.
     *
     * @deprecated Use FileDiskService::registerDisk() instead — setConfig() mutates filesystems.default.
     */
    public function setConfig(): void
    {
        $service = app(FileDiskService::class);
        $diskName = $service->registerDisk($this);
        config(['filesystems.default' => $diskName]);
    }

    /**
     * Determine whether this disk is configured as the default storage disk.
     */
    public function setAsDefault(): bool
    {
        return $this->set_as_default;
    }

    /**
     * Register a dynamic filesystem disk in the runtime configuration using the given credentials.
     *
     * @deprecated Use FileDisk::find($id)->registerDisk() instead.
     */
    public static function setFilesystem(Collection $credentials, string $driver): void
    {
        $prefix = env('DYNAMIC_DISK_PREFIX', 'temp_');

        config(['filesystems.default' => $prefix.$driver]);

        $disks = config('filesystems.disks.'.$driver);

        foreach ($disks as $key => $value) {
            if ($credentials->has($key)) {
                $disks[$key] = $credentials[$key];
            }
        }

        if ($driver === 'local' && isset($disks['root']) && ! str_starts_with($disks['root'], '/')) {
            $disks['root'] = storage_path('app/'.$disks['root']);
        }

        config(['filesystems.disks.'.$prefix.$driver => $disks]);
    }

    public function isSystem(): bool
    {
        return $this->type === self::DISK_TYPE_SYSTEM;
    }

    public function isRemote(): bool
    {
        return $this->type === self::DISK_TYPE_REMOTE;
    }
}
