<?php

namespace App\Platform\Modules\Application;

use App\Domains\Accounts\Models\Company;
use Illuminate\Support\Str;
use InvoiceShelf\Modules\Registry;
use Nwidart\Modules\Facades\Module;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Models;

/**
 * Settles the Bouncer side of a module's lifecycle.
 *
 * A module's abilities are namespaced `{slug}:{ability}` and never subject to a
 * model, so the slug is the only handle needed to find them again. Enabling a
 * module hands its abilities to every company's `owner` role; uninstalling it
 * takes the ability rows away entirely. Disabling deliberately does neither:
 * the grants stay put so a module switched back on finds its permissions the
 * way its owners left them.
 */
class ModuleAbilitySync
{
    /** The role every company is created with, and the only one granted here. */
    private const OWNER_ROLE = 'owner';

    /**
     * Grant a module's abilities to every company's owner role.
     *
     * Bouncer's own writes are idempotent -- the ability row is found or
     * created, and an already-associated ability is diffed away before the
     * pivot insert -- so repeating an enable changes nothing.
     *
     * @param  list<array{ability: string, name: string, model: null, depends_on: list<string>, owner_only: bool}>  $entries
     */
    public function grant(string $slug, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        foreach (Company::query()->pluck('id') as $companyId) {
            BouncerFacade::scope()->onceTo($companyId, function () use ($companyId, $entries): void {
                $owner = Models::role()->newQuery()
                    ->where('name', self::OWNER_ROLE)
                    ->where('scope', $companyId)
                    ->first();

                if ($owner === null) {
                    return;
                }

                foreach ($entries as $entry) {
                    BouncerFacade::allow($owner)->to($entry['ability']);
                }
            });
        }

        BouncerFacade::refresh();
    }

    /**
     * Remove every trace of a module's abilities, in every company.
     *
     * Run without a scope on purpose: the ability rows were written one per
     * company, and an uninstall has to clear all of them, not just the ones
     * belonging to whichever company the caller happens to be acting for.
     */
    public function revoke(string $slug): void
    {
        BouncerFacade::scope()->removeOnce(function () use ($slug): void {
            $abilityIds = Models::ability()->newQuery()
                ->where('name', 'like', $slug.':%')
                ->pluck('id');

            if ($abilityIds->isEmpty()) {
                return;
            }

            Models::query('permissions')->whereIn('ability_id', $abilityIds)->delete();
            Models::ability()->newQuery()->whereKey($abilityIds)->delete();
        });

        BouncerFacade::refresh();
    }

    /**
     * The abilities a module has registered with the SDK.
     *
     * A module is enabled in a request where its providers never booted, so an
     * empty registry is the normal first answer rather than a verdict. The
     * runtime is registered once to give the module's provider its chance --
     * the application is already booted by then, so registering also boots --
     * and the registry is read again.
     *
     * @return list<array{ability: string, name: string, model: null, depends_on: list<string>, owner_only: bool}>
     */
    public function entriesFor(string $name, string $slug): array
    {
        $entries = Registry::abilitiesFor($slug);

        if ($entries !== []) {
            return $entries;
        }

        Module::find($name)?->register();

        return Registry::abilitiesFor($slug);
    }

    /**
     * The slug a module's abilities are namespaced under.
     *
     * The registry row is the authority, but rows written before the column
     * existed carry none, so the module's own manifest is consulted next and
     * the kebab-cased studly name is the last resort.
     */
    public function slugFor(object $module): string
    {
        $slug = $module->slug ?? null;

        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $name = (string) ($module->name ?? '');
        $manifestSlug = $name === '' ? null : Module::find($name)?->get('slug');

        if (is_string($manifestSlug) && $manifestSlug !== '') {
            return $manifestSlug;
        }

        return Str::kebab($name);
    }
}
