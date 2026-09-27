<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\Quote;
use App\Domains\Sales\Models\QuoteItem;
use App\Platform\Modules\Models\MarketplaceCredential;
use App\Platform\Modules\Models\MarketplaceOperation;
use App\Platform\Modules\Models\Module;
use App\Platform\Persistence\ModelIdentityMap;
use Illuminate\Database\Eloquent\Relations\Relation;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Database\Role;

test('first-party and bouncer models use stable morph aliases', function () {
    expect(ModelIdentityMap::aliases())
        ->toHaveKey('company', Company::class)
        ->toHaveKey('customer', Customer::class)
        ->toHaveKey('invoice', Invoice::class)
        ->toHaveKey('quote', Quote::class)
        ->toHaveKey('quote_item', QuoteItem::class)
        ->toHaveKey('marketplace_credential', MarketplaceCredential::class)
        ->toHaveKey('marketplace_operation', MarketplaceOperation::class)
        ->toHaveKey('module', Module::class)
        ->toHaveKey('user', User::class)
        ->toHaveKey('bouncer_ability', Ability::class)
        ->toHaveKey('bouncer_role', Role::class);

    expect((new Invoice)->getMorphClass())->toBe('invoice')
        ->and((new User)->getMorphClass())->toBe('user')
        ->and((new Role)->getMorphClass())->toBe('bouncer_role')
        ->and(Relation::getMorphedModel('customer'))->toBe(Customer::class);
});

test('every concrete first-party model has a stable identity', function () {
    $models = collect([app_path('Models'), app_path('Domains'), app_path('Platform')])
        ->filter(fn (string $directory): bool => is_dir($directory))
        ->flatMap(function (string $directory): array {
            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                $path = str_replace('\\', '/', $file->getPathname());

                if ($file->isFile() && $file->getExtension() === 'php' && str_contains($path, '/Models/')) {
                    $relative = substr($path, strlen(str_replace('\\', '/', app_path())) + 1);
                    $files[] = 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));
                }
            }

            // Abstract proposal bases have no rows or persisted morph identity.
            return array_values(array_filter($files, fn (string $model): bool => ! (new ReflectionClass($model))->isAbstract()));
        })
        ->values();

    $mappedModels = collect(ModelIdentityMap::aliases())
        ->values()
        ->filter(fn (string $model): bool => str_starts_with($model, 'App\\'))
        ->values();

    expect($models->diff($mappedModels)->values()->all())->toBe([])
        ->and($mappedModels->diff($models)->values()->all())->toBe([]);
});

test('first-party models have canonical owners and explicit table contracts', function () {
    expect(is_dir(app_path('Models')))->toBeFalse();

    collect(ModelIdentityMap::aliases())
        ->values()
        ->filter(fn (string $model): bool => str_starts_with($model, 'App\\'))
        ->each(function (string $model): void {
            $table = new ReflectionProperty($model, 'table');

            expect($table->getDeclaringClass()->getName())->toBe($model)
                ->and((new $model)->getTable())->not->toBeEmpty();
        });
});

test('database aliases do not leak through the existing v1 discriminator', function () {
    expect(ModelIdentityMap::publicType('invoice'))->toBe('App\\Models\\Invoice')
        ->and(ModelIdentityMap::publicType('Modules\\Example\\Models\\Record'))
        ->toBe('Modules\\Example\\Models\\Record');
});
