<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $version = preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));

        $dbDriver = config('database.default');
        $dbVersion = $this->getDatabaseVersion($dbDriver);

        return response()->json([
            'app_version' => $version,
            'php_version' => phpversion(),
            'database' => [
                'driver' => $dbDriver,
                'version' => $dbVersion,
            ],
            'counts' => [
                'companies' => Company::count(),
                'users' => User::count(),
            ],
        ]);
    }

    private function getDatabaseVersion(string $driver): ?string
    {
        try {
            return match ($driver) {
                'mysql' => DB::selectOne('SELECT VERSION() as version')?->version,
                'pgsql' => DB::selectOne('SHOW server_version')?->server_version,
                'sqlite' => DB::selectOne('SELECT sqlite_version() as version')?->version,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
