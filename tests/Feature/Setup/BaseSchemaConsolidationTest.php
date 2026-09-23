<?php

// The base-schema consolidation: what it builds, what it leaves alone, and what
// it refuses. Spec: squash-guard-spec.md ("Acceptance tests").
//
// Every test here works on its own throwaway SQLite file rather than on the
// suite's database, because the point is to watch the migration meet databases
// in states the suite's own database can never be in — half-migrated, restored
// without its history, or completely empty.

use App\Platform\Operations\Database\SchemaConsolidationGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one migration under test, as `migrate --path` wants it.
 */
function consolidationPath(): string
{
    return 'database/migrations/'.SchemaConsolidationGuard::CONSOLIDATION_MIGRATION.'.php';
}

/**
 * Run the consolidation, and nothing else, against the throwaway database.
 */
function runConsolidation(): void
{
    Artisan::call('migrate', [
        '--database' => 'squash',
        '--path' => consolidationPath(),
        '--force' => true,
    ]);
}

/**
 * Give the throwaway database a migration repository holding these names.
 */
function recordHistory(array $names): void
{
    Artisan::call('migrate:install', ['--database' => 'squash']);

    DB::connection('squash')->table('migrations')->insert(
        array_map(fn (string $name): array => ['migration' => $name, 'batch' => 1], $names)
    );
}

/**
 * Names in the repository that belong to code this file knows nothing about.
 *
 * Module migrations share the repository table with the application's own, and
 * a database can hold them from any point in its life. The decision ignores
 * them and the prune never claims them, whatever they are called.
 */
function foreignHistory(): array
{
    return [
        '2022_06_01_120000_create_payments_module_tables',
        '2025_11_04_090000_create_bank_feed_module_tables',
    ];
}

/**
 * The v3-era migrations, read off the tree they live in.
 *
 * These are the names an installation of a 3.0.0 alpha recorded beside the 150:
 * the migrations this release still ships, which run after the consolidation
 * and must survive it. Reading the directory rather than listing them keeps the
 * fixture honest as the v3 line grows.
 */
function v3EraHistory(): array
{
    $names = array_map(
        fn (string $file): string => basename($file, '.php'),
        glob(base_path('database/migrations/*.php'))
    );

    return array_values(array_diff($names, [SchemaConsolidationGuard::CONSOLIDATION_MIGRATION]));
}

/**
 * Every name the repository holds, on the throwaway database.
 */
function recordedHistory(): array
{
    return DB::connection('squash')->table('migrations')->pluck('migration')->all();
}

/**
 * Stand in for "this database already has a schema".
 */
function giveDatabaseASentinel(): void
{
    Schema::connection('squash')->create('companies', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
    });
}

/**
 * Everything about the database that an ABORT must leave untouched.
 */
function databaseFootprint(): array
{
    $builder = Schema::connection('squash');
    $footprint = [];

    foreach ($builder->getTables() as $table) {
        $footprint[$table['name']] = [
            'columns' => array_column($builder->getColumns($table['name']), 'name'),
            'rows' => DB::connection('squash')->table($table['name'])->get()->toArray(),
        ];
    }

    ksort($footprint);

    return $footprint;
}

/**
 * The four introspection calls the boundary fixture was captured with.
 */
function introspectConsolidatedSchema(): array
{
    $builder = Schema::connection('squash');
    $tables = [];

    foreach ($builder->getTables() as $table) {
        $name = $table['name'];

        if ($name === 'migrations') {
            continue;
        }

        $tables[$name] = [
            'columns' => array_values($builder->getColumns($name)),
            'indexes' => array_values($builder->getIndexes($name)),
            'foreign_keys' => array_values($builder->getForeignKeys($name)),
        ];
    }

    return normaliseIntrospection($tables);
}

/**
 * Put an introspection result in a comparable shape.
 *
 * Column order is compared as it stands — it is part of what the consolidation
 * reproduces. Index and foreign-key order is not: the drivers report them in
 * whatever order they please, so both are sorted. The schema each foreign key
 * names is dropped, because it is the database's own name and the fixture was
 * captured from a database with a different one.
 */
function normaliseIntrospection(array $tables): array
{
    $normalised = [];

    foreach ($tables as $name => $table) {
        $indexes = array_values($table['indexes']);
        usort($indexes, fn (array $a, array $b): int => [$a['name'], implode(',', $a['columns'])]
            <=> [$b['name'], implode(',', $b['columns'])]);

        $foreignKeys = array_map(function (array $key): array {
            unset($key['foreign_schema']);

            return $key;
        }, array_values($table['foreign_keys']));
        usort($foreignKeys, fn (array $a, array $b): int => [implode(',', $a['columns']), $a['foreign_table']]
            <=> [implode(',', $b['columns']), $b['foreign_table']]);

        $normalised[$name] = [
            'columns' => array_values($table['columns']),
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];
    }

    ksort($normalised);

    return $normalised;
}

/**
 * The captured end state of the replaced chain on SQLite.
 */
function boundaryFixture(): array
{
    $fixture = json_decode(file_get_contents(__DIR__.'/fixtures/boundary.sqlite.json'), true);

    return normaliseIntrospection($fixture['tables']);
}

beforeEach(function () {
    $this->squashDatabase = tempnam(sys_get_temp_dir(), 'squash-').'.sqlite';
    touch($this->squashDatabase);

    config(['database.connections.squash' => [
        'driver' => 'sqlite',
        'database' => $this->squashDatabase,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    DB::purge('squash');
});

afterEach(function () {
    DB::purge('squash');
    @unlink($this->squashDatabase);
});

// -- BUILD ------------------------------------------------------------------

it('builds the boundary schema on an empty database', function () {
    runConsolidation();

    $built = introspectConsolidatedSchema();
    $expected = boundaryFixture();

    expect(array_keys($built))->toBe(array_keys($expected))
        ->and(count($built))->toBe(43)
        ->and(array_sum(array_map(fn (array $t): int => count($t['columns']), $built)))->toBe(524);

    foreach ($expected as $name => $table) {
        expect($built[$name]['columns'])->toEqual($table['columns']);
        expect($built[$name]['indexes'])->toEqual($table['indexes']);
        expect($built[$name]['foreign_keys'])->toEqual($table['foreign_keys']);
    }
});

it('seeds the two system file disks', function () {
    runConsolidation();

    $disks = DB::connection('squash')->table('file_disks')->orderBy('id')->get();

    expect($disks)->toHaveCount(2)
        ->and($disks[0]->name)->toBe('public')
        ->and($disks[0]->type)->toBe('SYSTEM')
        ->and($disks[0]->driver)->toBe('local')
        ->and((bool) $disks[0]->set_as_default)->toBeFalse()
        ->and($disks[1]->name)->toBe('local_private')
        ->and($disks[1]->type)->toBe('SYSTEM')
        ->and($disks[1]->driver)->toBe('local')
        ->and((bool) $disks[1]->set_as_default)->toBeTrue();

    // Credentials are a single JSON encoding, and describe this installation
    // rather than the one the release was built on.
    expect(json_decode($disks[0]->credentials, true))->toBe([
        'driver' => 'local',
        'root' => config('filesystems.disks.public.root'),
        'url' => config('app.url').'/storage',
        'visibility' => 'public',
    ]);

    expect(json_decode($disks[1]->credentials, true))->toBe([
        'root' => config('filesystems.disks.local.root'),
        'driver' => 'local',
    ]);
});

/**
 * The consolidation used to insert three currencies here, which only ever ran
 * against an empty database and so only ever ran ahead of
 * `CurrenciesTableSeeder`. The result was three duplicated rows on every fresh
 * install. The seeder owns the whole list now, so this step is gone.
 */
it('leaves the currency list to the seeder', function () {
    runConsolidation();

    expect(DB::connection('squash')->table('currencies')->count())->toBe(0);
});

it('stamps the boundary version', function () {
    runConsolidation();

    expect(DB::connection('squash')->table('settings')->where('option', 'version')->value('value'))
        ->toBe('1.3.0');
});

// -- SKIP -------------------------------------------------------------------

it('leaves the schema of a fully migrated 2.4.x database alone', function () {
    recordHistory([...SchemaConsolidationGuard::REPLACED_MIGRATIONS, ...foreignHistory()]);
    giveDatabaseASentinel();
    DB::connection('squash')->table('companies')->insert(['name' => 'Acme']);

    $before = databaseFootprint();

    runConsolidation();

    $after = databaseFootprint();

    // Nothing outside the repository moved: same tables, same rows in them.
    expect(array_keys($after))->toBe(array_keys($before))
        ->and($after['companies'])->toEqual($before['companies'])
        ->and(DB::connection('squash')->table('migrations')
            ->where('migration', SchemaConsolidationGuard::CONSOLIDATION_MIGRATION)->exists())->toBeTrue();

    // No schema was built: the sentinel is still the stub, and none of the
    // boundary tables appeared beside it.
    expect(Schema::connection('squash')->hasTable('invoices'))->toBeFalse()
        ->and($after['companies']['columns'])->toBe(['id', 'name']);
});

/**
 * The history a 2.4.x database actually arrives with: the full replaced chain,
 * the one name the 2.x line shipped past it, and whatever its modules recorded.
 * Two of those three are this file's to retire.
 */
it('retires the replaced history of a 2.4.x database', function () {
    recordHistory([
        ...SchemaConsolidationGuard::REPLACED_MIGRATIONS,
        SchemaConsolidationGuard::SUPERSEDED_MIGRATION,
        ...foreignHistory(),
    ]);
    giveDatabaseASentinel();

    runConsolidation();

    $recorded = recordedHistory();

    // Every replaced name is gone, and so is the 2.4.x-only one beside them.
    expect(array_intersect(SchemaConsolidationGuard::REPLACED_MIGRATIONS, $recorded))->toBe([])
        ->and($recorded)->not->toContain(SchemaConsolidationGuard::SUPERSEDED_MIGRATION);

    // What is left is precisely what this file never claimed, plus itself.
    expect($recorded)->toEqualCanonicalizing([
        ...foreignHistory(),
        SchemaConsolidationGuard::CONSOLIDATION_MIGRATION,
    ]);
});

/**
 * The other database that reaches SKIP: an installation of a 3.0.0 alpha, which
 * ran the 150 as separate files and then the v3-era ones on top. Only the 150
 * are stale — the v3-era migrations still ship, and a database that forgot them
 * would run them a second time.
 */
it('keeps the v3-era history when an alpha database is consolidated', function () {
    recordHistory([...SchemaConsolidationGuard::REPLACED_MIGRATIONS, ...v3EraHistory()]);
    giveDatabaseASentinel();

    runConsolidation();

    $recorded = recordedHistory();

    expect(array_intersect(SchemaConsolidationGuard::REPLACED_MIGRATIONS, $recorded))->toBe([])
        ->and($recorded)->toEqualCanonicalizing([
            ...v3EraHistory(),
            SchemaConsolidationGuard::CONSOLIDATION_MIGRATION,
        ]);
});

it('ignores recorded names that are not part of the replaced set', function () {
    recordHistory(foreignHistory());

    // Nothing from the replaced set is present, and there is no schema, so this
    // is still a fresh database however many other names it carries.
    expect(SchemaConsolidationGuard::inspect('squash')->mode())->toBe(SchemaConsolidationGuard::BUILD);

    runConsolidation();

    expect(Schema::connection('squash')->hasTable('invoices'))->toBeTrue();
});

// -- ABORT: floor -----------------------------------------------------------

it('refuses a database that stopped one migration short of the 2.x boundary', function () {
    $history = SchemaConsolidationGuard::REPLACED_MIGRATIONS;
    array_pop($history);

    recordHistory([...$history, ...foreignHistory()]);
    giveDatabaseASentinel();

    $before = databaseFootprint();

    expect(fn () => runConsolidation())->toThrow(
        RuntimeException::class,
        'Upgrading to this version requires the database to be fully migrated on the 2.x line first. '
        .'This database has run 149 of the 150 historical migrations this version consolidates. '
        .'Install the latest InvoiceShelf 2.x release, run its migrations to completion, then upgrade '
        .'to this version again. No changes have been made.'
    );

    expect(databaseFootprint())->toEqual($before);
});

it('refuses a database that only ever ran the first migration', function () {
    recordHistory([SchemaConsolidationGuard::REPLACED_MIGRATIONS[0]]);

    $before = databaseFootprint();

    expect(fn () => runConsolidation())->toThrow(
        RuntimeException::class,
        'This database has run 1 of the 150 historical migrations this version consolidates.'
    );

    expect(databaseFootprint())->toEqual($before);
});

/**
 * One replaced name genuinely contains a space. Matching is byte-exact, so a
 * database recording the tidied-up spelling has not run that migration.
 */
it('matches replaced names byte for byte', function () {
    $spaced = '2018_11_02_133825_create_ expense_categories_table';

    expect(SchemaConsolidationGuard::REPLACED_MIGRATIONS)->toContain($spaced);

    $history = array_map(
        fn (string $name): string => $name === $spaced ? str_replace(' ', '', $name) : $name,
        SchemaConsolidationGuard::REPLACED_MIGRATIONS
    );

    recordHistory($history);
    giveDatabaseASentinel();

    // 149 of 150 matched: the de-spaced name counts for nothing.
    $verdict = SchemaConsolidationGuard::inspect('squash');

    expect($verdict->mode())->toBe(SchemaConsolidationGuard::ABORT_FLOOR)
        ->and($verdict->recordedCount())->toBe(149);
});

// -- ABORT: inconsistent ----------------------------------------------------

it('refuses a schema that arrived without its history', function () {
    recordHistory([]);
    giveDatabaseASentinel();

    $before = databaseFootprint();

    expect(fn () => runConsolidation())->toThrow(
        RuntimeException::class,
        'The database schema and the migration history do not match (the recorded history and the '
        .'`companies` table disagree). This usually means a partial restore. Restore a consistent '
        .'backup — schema and migration history together — and run the upgrade again. No changes '
        .'have been made.'
    );

    expect(databaseFootprint())->toEqual($before);
});

it('refuses a history that arrived without its schema', function () {
    recordHistory([...SchemaConsolidationGuard::REPLACED_MIGRATIONS, ...foreignHistory()]);

    $before = databaseFootprint();

    expect(fn () => runConsolidation())->toThrow(
        RuntimeException::class,
        'The database schema and the migration history do not match'
    );

    expect(databaseFootprint())->toEqual($before);
});

// -- Re-running and rolling back --------------------------------------------

it('does nothing when migrate runs a second time', function () {
    runConsolidation();

    $before = databaseFootprint();

    runConsolidation();

    expect(databaseFootprint())->toEqual($before);
});

/**
 * The same question on the other side of the decision: once the history has
 * been pruned, the database looks to the guard exactly like the inconsistency
 * it refuses — schema, no replaced names. The preflight short-circuit is what
 * stops that from mattering, and the recorded consolidation is what stops the
 * migration from being offered again at all.
 */
it('does nothing when migrate runs a second time on a pruned history', function () {
    recordHistory([
        ...SchemaConsolidationGuard::REPLACED_MIGRATIONS,
        SchemaConsolidationGuard::SUPERSEDED_MIGRATION,
        ...foreignHistory(),
    ]);
    giveDatabaseASentinel();

    runConsolidation();

    $before = databaseFootprint();

    runConsolidation();

    expect(databaseFootprint())->toEqual($before)
        ->and(SchemaConsolidationGuard::preflight('squash'))->toBeNull();
});

it('refuses to be rolled back', function () {
    runConsolidation();

    $before = databaseFootprint();

    expect(fn () => Artisan::call('migrate:rollback', [
        '--database' => 'squash',
        '--path' => consolidationPath(),
        '--force' => true,
    ]))->toThrow(RuntimeException::class, 'irreversible');

    expect(databaseFootprint())->toEqual($before);
});

// -- The decision table, read directly --------------------------------------

it('decides every row of the decision table from reads alone', function (
    bool $withHistory,
    bool $partial,
    bool $withSentinel,
    string $expected,
) {
    $history = [];

    if ($withHistory) {
        $history = SchemaConsolidationGuard::REPLACED_MIGRATIONS;

        if ($partial) {
            array_pop($history);
        }
    }

    recordHistory($history);

    if ($withSentinel) {
        giveDatabaseASentinel();
    }

    expect(SchemaConsolidationGuard::inspect('squash')->mode())->toBe($expected);
})->with([
    'empty history, no schema' => [false, false, false, SchemaConsolidationGuard::BUILD],
    'empty history, schema present' => [false, false, true, SchemaConsolidationGuard::ABORT_INCONSISTENT],
    'partial history, schema present' => [true, true, true, SchemaConsolidationGuard::ABORT_FLOOR],
    'partial history, no schema' => [true, true, false, SchemaConsolidationGuard::ABORT_FLOOR],
    'complete history, schema present' => [true, false, true, SchemaConsolidationGuard::SKIP],
    'complete history, no schema' => [true, false, false, SchemaConsolidationGuard::ABORT_INCONSISTENT],
]);

it('embeds the replaced set exactly once, at full length', function () {
    $replaced = SchemaConsolidationGuard::REPLACED_MIGRATIONS;

    expect($replaced)->toHaveCount(150)
        ->and(array_unique($replaced))->toHaveCount(150)
        ->and($replaced[0])->toBe('2014_10_11_071840_create_companies_table')
        ->and(end($replaced))->toBe('2025_09_02_add_expense_number_to_expenses_table');

    // None of them is still on disk: this migration is what replaced them.
    foreach ($replaced as $name) {
        expect(file_exists(base_path('database/migrations/'.$name.'.php')))->toBeFalse();
    }

    expect(glob(base_path('database/migrations/*.php')))
        ->each(fn ($file) => expect(basename($file->value))->toStartWith('2026_'));
});

it('offers the prune exactly the replaced set plus the one 2.4.x-only name', function () {
    $stale = SchemaConsolidationGuard::staleRecordedMigrations();

    expect($stale)->toHaveCount(151)
        ->and(array_unique($stale))->toHaveCount(151)
        ->and($stale)->toContain(SchemaConsolidationGuard::SUPERSEDED_MIGRATION)
        ->and(SchemaConsolidationGuard::REPLACED_MIGRATIONS)
        ->not->toContain(SchemaConsolidationGuard::SUPERSEDED_MIGRATION)
        ->and($stale)->not->toContain(SchemaConsolidationGuard::CONSOLIDATION_MIGRATION);

    // The extra name is pruned precisely because no file here answers to it.
    expect(file_exists(base_path(
        'database/migrations/'.SchemaConsolidationGuard::SUPERSEDED_MIGRATION.'.php'
    )))->toBeFalse();

    // And nothing this release still ships is on the list.
    expect(array_intersect($stale, v3EraHistory()))->toBe([]);
});
