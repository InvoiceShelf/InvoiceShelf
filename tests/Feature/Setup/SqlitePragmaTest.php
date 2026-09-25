<?php

use Illuminate\Support\Facades\DB;

/*
 * SQLite connection pragmas come from the environment: a busy timeout for
 * everyone, and WAL with NORMAL sync for installs that opt in (the managed
 * cloud does; WAL is unsafe on the network filesystems some self-hosters use).
 */

function sqliteConfigWith(array $env): array
{
    foreach ($env as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    try {
        return (require config_path('database.php'))['connections']['sqlite'];
    } finally {
        foreach (array_keys($env) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}

test('the busy timeout defaults to five seconds', function () {
    expect(config('database.connections.sqlite.busy_timeout'))->toBe(5000)
        ->and(config('database.connections.sqlite.journal_mode'))->toBeNull()
        ->and(config('database.connections.sqlite.synchronous'))->toBeNull();
});

test('journal mode, sync and timeout follow the environment', function () {
    $config = sqliteConfigWith(['DB_JOURNAL_MODE' => 'wal', 'DB_SYNCHRONOUS' => 'normal', 'DB_BUSY_TIMEOUT' => '2500']);

    expect($config['journal_mode'])->toBe('wal')
        ->and($config['synchronous'])->toBe('normal')
        ->and($config['busy_timeout'])->toBe(2500);
});

test('a file database opens in WAL mode when asked', function () {
    $path = tempnam(sys_get_temp_dir(), 'pragma').'.sqlite';
    touch($path);

    config(['database.connections.pragma_probe' => [
        ...config('database.connections.sqlite'),
        'database' => $path,
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
    ]]);

    try {
        expect(strtolower(DB::connection('pragma_probe')->selectOne('PRAGMA journal_mode')->journal_mode))->toBe('wal')
            ->and((int) DB::connection('pragma_probe')->selectOne('PRAGMA busy_timeout')->timeout)->toBe(5000);
    } finally {
        DB::purge('pragma_probe');
        @unlink($path);
        @unlink($path.'-wal');
        @unlink($path.'-shm');
    }
});
