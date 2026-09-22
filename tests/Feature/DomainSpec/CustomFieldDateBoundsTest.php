<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Metadata\Models\CustomField;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * Date fields accepted any date at all, which is the weakest part of the
 * feature given #237 exists because a delivered-on date has to be real.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

function dateFieldWith(array $validation, string $type = 'Date'): CustomField
{
    return CustomField::factory()->create([
        'company_id' => test()->company->id,
        'model_type' => 'Item',
        'type' => $type,
        'label' => 'Delivered on',
        'is_required' => false,
        'validation' => $validation,
    ]);
}

function answer(CustomField $field, string $value): TestResponse
{
    return postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['id' => $field->id, 'value' => $value]],
    ]));
}

test('a date outside a fixed window is refused', function (string $value) {
    $field = dateFieldWith(['earliest' => '2026-01-01', 'latest' => '2026-12-31']);

    answer($field, $value)->assertJsonValidationErrors('customFields.0.value');
})->with([
    'before the window' => ['2025-12-31'],
    'after the window' => ['2027-01-01'],
]);

test('a date inside a fixed window is kept', function () {
    $field = dateFieldWith(['earliest' => '2026-01-01', 'latest' => '2026-12-31']);

    answer($field, '2026-06-15')->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'date_answer' => '2026-06-15',
    ]);
});

test('latest today refuses tomorrow and accepts today', function () {
    $field = dateFieldWith(['latest' => 'today']);

    answer($field, now()->addDay()->toDateString())
        ->assertJsonValidationErrors('customFields.0.value');

    answer($field, now()->toDateString())->assertSuccessful();
});

test('earliest today refuses yesterday', function () {
    $field = dateFieldWith(['earliest' => 'today']);

    answer($field, now()->subDay()->toDateString())
        ->assertJsonValidationErrors('customFields.0.value');
});

test('today is the company today, not the server today', function () {
    // The point of the whole timezone dance, pinned to an instant where the
    // two disagree rather than left to the luck of the clock: at 13:00 UTC
    // Auckland has already turned over to the next day.
    $this->travelTo(Carbon::parse('2026-06-15 13:00:00', 'UTC'));

    CompanySetting::setSettings(['time_zone' => 'Pacific/Auckland'], $this->company->id);

    $field = dateFieldWith(['latest' => 'today']);

    expect(Carbon::today('Pacific/Auckland')->toDateString())->toBe('2026-06-16')
        ->and(Carbon::today('UTC')->toDateString())->toBe('2026-06-15');

    // A company living on the 16th must not be told the 16th is in the
    // future. Resolving either side in the server's zone refuses this.
    answer($field, '2026-06-16')->assertSuccessful();
});

test('a time outside its window is refused and one inside is kept', function () {
    $field = dateFieldWith(['earliest' => '09:00', 'latest' => '17:00'], 'Time');

    answer($field, '08:30')->assertJsonValidationErrors('customFields.0.value');
    answer($field, '18:00')->assertJsonValidationErrors('customFields.0.value');
    answer($field, '09:30')->assertSuccessful();
});

test('a datetime is bounded to the whole of the latest day', function () {
    $field = dateFieldWith(['latest' => 'today'], 'DateTime');

    // "Today" is a day, not an instant, so any time today is inside it.
    answer($field, now()->setTime(23, 30)->format('Y-m-d H:i'))->assertSuccessful();
});

test('a date field with no bounds accepts anything, as every existing one does', function () {
    $field = dateFieldWith([]);

    answer($field, '1999-01-01')->assertSuccessful();
});

test('a bound that is not a date is refused when the field is defined', function () {
    postJson('api/v1/custom-fields', CustomField::factory()->raw([
        'type' => 'Date',
        'validation' => ['earliest' => 'the day before the thing'],
    ]))->assertJsonValidationErrors('validation.earliest');
});

test('a latest before its earliest is refused when the field is defined', function () {
    postJson('api/v1/custom-fields', CustomField::factory()->raw([
        'type' => 'Date',
        'validation' => ['earliest' => '2026-12-31', 'latest' => '2026-01-01'],
    ]))->assertJsonValidationErrors('validation.latest');
});
