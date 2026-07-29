<?php

use App\Models\User;
use App\Rules\PdfTemplateExists;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * template_name was validated only as `required`, so any string was stored and
 * the failure surfaced much later as a raw "view not found" 500 when someone
 * tried to generate the PDF.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

test('the rule accepts a template the picker offers', function () {
    $validator = Validator::make(
        ['template_name' => 'invoice1'],
        ['template_name' => [new PdfTemplateExists('invoice')]]
    );

    expect($validator->fails())->toBeFalse();
});

test('the rule rejects a template that does not exist', function () {
    $validator = Validator::make(
        ['template_name' => 'no-such-template'],
        ['template_name' => [new PdfTemplateExists('invoice')]]
    );

    expect($validator->fails())->toBeTrue();
});

/**
 * The types are separate directories, so an estimate template is not a valid
 * invoice template even though both exist.
 */
test('the rule is scoped to the document type', function () {
    $validator = Validator::make(
        ['template_name' => 'estimate1'],
        ['template_name' => [new PdfTemplateExists('invoice')]]
    );

    expect($validator->fails())->toBeTrue();
});

test('creating an invoice with an unknown template fails validation, not at render time', function () {
    postJson('/api/v1/invoices', [
        'invoice_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'customer_id' => 1,
        'template_name' => 'definitely-not-a-template',
        'items' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('template_name');
});
