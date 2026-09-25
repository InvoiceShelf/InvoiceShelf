<?php

use App\Domains\Accounts\Http\Controllers\Admin\AbilitiesController;
use App\Domains\Accounts\Http\Controllers\Admin\CompaniesController;
use App\Domains\Accounts\Http\Controllers\Admin\RolePresetsController;
use App\Domains\Accounts\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('companies', [CompaniesController::class, 'index']);
Route::get('companies/{company}', [CompaniesController::class, 'show']);
Route::get('companies/{company}/roles', [CompaniesController::class, 'roles']);
Route::put('companies/{company}', [CompaniesController::class, 'update']);
Route::get('users', [UsersController::class, 'index']);
Route::post('users', [UsersController::class, 'store']);
Route::get('users/{user}', [UsersController::class, 'show']);
Route::put('users/{user}', [UsersController::class, 'update']);
Route::post('users/{user}/impersonate', [UsersController::class, 'impersonate']);
Route::get('abilities', AbilitiesController::class);
Route::apiResource('role-presets', RolePresetsController::class)->except('show');
