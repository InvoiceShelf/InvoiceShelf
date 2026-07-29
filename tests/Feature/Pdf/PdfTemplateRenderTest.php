<?php

use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * Renders every stock PDF template end-to-end through the real pdf routes.
 *
 * These templates are Blade, so class references inside them (e.g.
 * ImageUtils::toBase64Src) are plain strings — invisible to IDE renames, to
 * Pint, and to every other check in CI. A move like #695's leaves the app fatal
 * on any company with a logo, and nothing catches it until a user generates an
 * invoice. Rendering each template here is the only thing that does.
 *
 * The template list is read off disk rather than hardcoded so a newly added
 * template is covered the moment it lands.
 */
function stockPdfTemplates(string $type): array
{
    // Datasets are resolved before the application is booted, so this cannot
    // use resource_path() or the File facade — plain glob only. The *.blade.php
    // pattern also keeps the partials/ subdirectory out of the list.
    $files = glob(dirname(__DIR__, 3)."/resources/views/app/pdf/{$type}/*.blade.php");

    return array_values(array_map(
        fn ($path) => basename($path, '.blade.php'),
        $files ?: []
    ));
}

/**
 * Asserts the route returned a PDF, starting at byte zero.
 *
 * This used to only assert %PDF appeared somewhere in the first kilobyte: the
 * body carried the status line and headers of an inner Response ahead of the
 * payload, because GeneratesPdfTrait wrapped $pdf->stream() — already a Response
 * — in another response()->make(). Readers scan for the header so nobody
 * noticed, but the bytes were malformed. The trait now passes ->output(), so the
 * position can be asserted, and a regression would be caught rather than
 * tolerated.
 */
function assertRenderedPdf(TestResponse $response): void
{
    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
}

dataset('invoice templates', fn () => stockPdfTemplates('invoice'));
dataset('estimate templates', fn () => stockPdfTemplates('estimate'));

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();

    Sanctum::actingAs($user, ['*']);

    // The logo collection lives on the `public` disk. Fake it so the run never
    // writes into (or clears) a developer's real storage/app/public — media ids
    // restart at 1 under RefreshDatabase and would otherwise collide with
    // whatever is already sitting there.
    Storage::fake('public');

    // Every template guards the logo behind `@if ($logo)` and falls back to the
    // company name, so without a logo attached the ImageUtils branch is never
    // reached and this suite would pass against the broken code.
    $this->company
        ->addMediaFromString(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ))
        ->usingFileName('logo.png')
        ->toMediaCollection('logo');
});

test('every stock invoice template renders a pdf with a company logo', function (string $template) {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'company_id' => $this->company->id,
            'template_name' => $template,
        ]);

    assertRenderedPdf(get("/invoices/pdf/{$invoice->unique_hash}"));
})->with('invoice templates');

test('every stock estimate template renders a pdf with a company logo', function (string $template) {
    $estimate = Estimate::factory()
        ->hasItems(1)
        ->create([
            'company_id' => $this->company->id,
            'template_name' => $template,
        ]);

    assertRenderedPdf(get("/estimates/pdf/{$estimate->unique_hash}"));
})->with('estimate templates');

test('the payment template renders a pdf with a company logo', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
    ]);

    assertRenderedPdf(get("/payments/pdf/{$payment->unique_hash}"));
});

/**
 * The assertions above prove the pipeline does not fatal. This one proves the
 * logo actually reached the markup as a base64 data URI — i.e. that
 * ImageUtils::toBase64Src resolved and ran, which is the specific regression
 * #695 fixed. Without it, a template that silently dropped the logo would still
 * emit a valid PDF and pass.
 */
test('the rendered invoice markup embeds the logo as a base64 data uri', function (string $template) {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'company_id' => $this->company->id,
            'template_name' => $template,
        ]);

    get("/invoices/pdf/{$invoice->unique_hash}?preview=true")
        ->assertOk()
        ->assertSee('src="data:image/png;base64,', false);
})->with('invoice templates');
