<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::query()->find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

test('a logo is stored under a name that no web server would run', function () {
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    postJson('/api/v1/company/upload-logo', [
        'company_logo' => json_encode(['name' => 'shell.php.png', 'data' => "data:image/png;base64,{$png}"]),
    ])->assertOk();

    expect($this->company->fresh()->getFirstMedia('logo')->file_name)->toBe('shell-php.png');
});
