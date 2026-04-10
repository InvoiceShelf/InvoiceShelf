<?php

namespace App\Http\Resources;

use App\Models\Module as ModelsModule;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $installedModule = ModelsModule::where('name', $this->module_name)->first();

        return [
            'id' => $this->id,
            'average_rating' => $this->average_rating,
            'cover' => $this->cover,
            'slug' => $this->slug,
            'module_name' => $this->module_name,
            'access_tier' => $this->access_tier ?? 'public',
            'faq' => $this->faq,
            'highlights' => $this->highlights,
            'installed_module_version' => $this->getInstalledModuleVersion($installedModule),
            'installed_module_version_updated_at' => $this->getInstalledModuleUpdatedAt($installedModule),
            'latest_module_version' => $this->latest_module_version,
            'latest_module_version_updated_at' => $this->latest_module_version_updated_at,
            'latest_min_invoiceshelf_version' => $this->latest_min_invoiceshelf_version ?? null,
            'latest_module_checksum_sha256' => $this->latest_module_checksum_sha256 ?? null,
            'is_dev' => $this->is_dev,
            'license' => $this->license,
            'long_description' => $this->long_description,
            'monthly_price' => $this->monthly_price,
            'name' => $this->name,
            'purchased' => $this->purchased ?? true,
            'reviews' => $this->reviews ?? [],
            'screenshots' => $this->screenshots,
            'short_description' => $this->short_description,
            'type' => $this->type,
            'yearly_price' => $this->yearly_price,
            'author_name' => $this->author_name,
            'author_avatar' => $this->author_avatar,
            'installed' => $this->moduleInstalled($installedModule),
            'enabled' => $this->moduleEnabled($installedModule),
            'update_available' => $this->updateAvailable($installedModule),
            'video_link' => $this->video_link,
            'video_thumbnail' => $this->video_thumbnail,
            'links' => $this->links,
        ];
    }

    public function getInstalledModuleVersion(?ModelsModule $installedModule): ?string
    {
        if ($installedModule && $installedModule->installed) {
            return $installedModule->version;
        }

        return null;
    }

    public function getInstalledModuleUpdatedAt(?ModelsModule $installedModule): ?string
    {
        if ($installedModule && $installedModule->installed) {
            return $installedModule->updated_at?->toIso8601String();
        }

        return null;
    }

    public function moduleInstalled(?ModelsModule $installedModule): bool
    {
        return (bool) ($installedModule?->installed);
    }

    public function moduleEnabled(?ModelsModule $installedModule): bool
    {
        return (bool) ($installedModule?->installed && $installedModule?->enabled);
    }

    public function updateAvailable(?ModelsModule $installedModule): bool
    {
        if (! $installedModule || ! $installedModule->installed) {
            return false;
        }

        if (! isset($this->latest_module_version) || ! is_string($this->latest_module_version)) {
            return false;
        }

        return version_compare($installedModule->version, $this->latest_module_version, '<');
    }
}
