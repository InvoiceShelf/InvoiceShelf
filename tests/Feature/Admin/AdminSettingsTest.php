<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('company settings config uses canonical v2 links', function () {
    $links = collect(config('invoiceshelf.setting_menu'))->pluck('link');

    expect($links)->toContain('/admin/settings/exchange-rate');
    expect($links)->toContain('/admin/settings/payment-modes');
    expect($links)->toContain('/admin/settings/expense-categories');
    expect($links)->toContain('/admin/settings/roles');
    expect($links)->toContain('/admin/settings/mail-config');

    expect($links)->not->toContain('/admin/settings/roles-settings');
    expect($links)->not->toContain('/admin/settings/exchange-rate-provider');
    expect($links)->not->toContain('/admin/settings/payment-mode');
    expect($links)->not->toContain('/admin/settings/expense-category');
    expect($links)->not->toContain('/admin/settings/mail-configuration');

    $bootstrapMenu = json_encode(getJson('/api/v1/bootstrap')
        ->assertOk()
        ->json('setting_menu'));

    expect($bootstrapMenu)->not->toContain('roles-settings');
    expect($bootstrapMenu)->not->toContain('exchange-rate-provider');
    expect($bootstrapMenu)->not->toContain('payment-mode');
    expect($bootstrapMenu)->not->toContain('expense-category');
    expect($bootstrapMenu)->not->toContain('mail-configuration');
});

test('super admin bootstrap uses administration mode when requested', function () {
    getJson('/api/v1/bootstrap?admin_mode=1')
        ->assertOk()
        ->assertJsonPath('admin_mode', true)
        ->assertJsonPath('current_company', null)
        ->assertJsonCount(0, 'setting_menu');
});

test('bootstrap without administration mode hydrates the selected company', function () {
    $companyId = User::find(1)->companies()->first()->id;

    getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('current_company.id', $companyId);
});

test('get global mail configuration', function () {
    getJson('/api/v1/mail/config')
        ->assertOk()
        ->assertJsonStructure([
            'mail_driver',
            'from_name',
            'from_mail',
        ]);
});

test('get global mail drivers returns capability-backed drivers', function () {
    $drivers = getJson('/api/v1/mail/drivers')
        ->assertOk()
        ->json();

    expect($drivers)->toContain('smtp', 'mail', 'sendmail', 'ses');

    if (class_exists(HttpClient::class) && class_exists(MailgunTransportFactory::class)) {
        expect($drivers)->toContain('mailgun');
    } else {
        expect($drivers)->not->toContain('mailgun');
    }

    if (class_exists(HttpClient::class) && class_exists(PostmarkTransportFactory::class)) {
        expect($drivers)->toContain('postmark');
    } else {
        expect($drivers)->not->toContain('postmark');
    }
});

test('save global mail configuration', function () {
    postJson('/api/v1/mail/config', [
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_username' => 'demo-user',
        'mail_password' => 'secret',
        'mail_encryption' => 'tls',
        'mail_scheme' => 'smtp',
        'mail_url' => 'smtp://smtp.example.com',
        'mail_timeout' => 30,
        'mail_local_domain' => 'invoiceshelf.test',
        'from_name' => 'InvoiceShelf',
        'from_mail' => 'hello@example.com',
    ])
        ->assertOk()
        ->assertJson([
            'success' => 'mail_variables_save_successfully',
        ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'mail_driver',
        'value' => 'smtp',
    ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'from_mail',
        'value' => 'hello@example.com',
    ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'mail_timeout',
        'value' => '30',
    ]);
});

test('save global postmark configuration', function () {
    postJson('/api/v1/mail/config', [
        'mail_driver' => 'postmark',
        'mail_postmark_token' => 'postmark-token',
        'mail_postmark_message_stream_id' => 'outbound',
        'from_name' => 'InvoiceShelf',
        'from_mail' => 'billing@example.com',
    ])
        ->assertOk()
        ->assertJson([
            'success' => 'mail_variables_save_successfully',
        ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'mail_driver',
        'value' => 'postmark',
    ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'mail_postmark_token',
        'value' => 'postmark-token',
    ]);
});

test('get pdf configuration', function () {
    getJson('/api/v1/pdf/config')
        ->assertOk()
        ->assertJsonStructure([
            'pdf_driver',
            'gotenberg_host',
            'pdf_paper_width',
            'pdf_paper_height',
            'pdf_orientation',
            'pdf_margin_top',
            'pdf_margin_right',
            'pdf_margin_bottom',
            'pdf_margin_left',
        ]);
});

/**
 * Page geometry is saved for whichever driver is selected. It used to hang off
 * gotenberg_papersize, so picking dompdf meant having no paper size at all and
 * switching drivers threw the setting away.
 */
test('save pdf configuration stores the page setup for dompdf too', function () {
    postJson('/api/v1/pdf/config', [
        'pdf_driver' => 'dompdf',
        'pdf_paper_width' => '8.5in',
        'pdf_paper_height' => '14in',
        'pdf_orientation' => 'landscape',
        'pdf_margin_top' => '5mm',
        'pdf_margin_right' => '6mm',
        'pdf_margin_bottom' => '7mm',
        'pdf_margin_left' => '8mm',
    ])
        ->assertOk()
        ->assertJson(['success' => 'pdf_variables_save_successfully']);

    foreach ([
        'pdf_driver' => 'dompdf',
        'pdf_paper_width' => '8.5in',
        'pdf_paper_height' => '14in',
        'pdf_orientation' => 'landscape',
        'pdf_margin_top' => '5mm',
        'pdf_margin_right' => '6mm',
        'pdf_margin_bottom' => '7mm',
        'pdf_margin_left' => '8mm',
    ] as $option => $value) {
        $this->assertDatabaseHas('settings', compact('option', 'value'));
    }
});

test('pdf configuration rejects a length with no unit', function () {
    postJson('/api/v1/pdf/config', [
        'pdf_driver' => 'dompdf',
        'pdf_paper_width' => '210',
        'pdf_paper_height' => '297mm',
        'pdf_orientation' => 'portrait',
    ])->assertStatus(422)->assertJsonValidationErrors('pdf_paper_width');
});

test('pdf configuration rejects an unknown orientation', function () {
    postJson('/api/v1/pdf/config', [
        'pdf_driver' => 'dompdf',
        'pdf_paper_width' => '210mm',
        'pdf_paper_height' => '297mm',
        'pdf_orientation' => 'sideways',
    ])->assertStatus(422)->assertJsonValidationErrors('pdf_orientation');
});

/**
 * A zero margin is a deliberate choice. AppConfigProvider guards its settings
 * with !empty(), and '0mm' is fine there, but a bare '0' would not be -- this
 * pins the behaviour either way.
 */
test('a zero margin survives the round trip', function () {
    postJson('/api/v1/pdf/config', [
        'pdf_driver' => 'dompdf',
        'pdf_paper_width' => '210mm',
        'pdf_paper_height' => '297mm',
        'pdf_orientation' => 'portrait',
        'pdf_margin_top' => '0mm',
        'pdf_margin_right' => '0mm',
        'pdf_margin_bottom' => '0mm',
        'pdf_margin_left' => '0mm',
    ])->assertOk();

    getJson('/api/v1/pdf/config')->assertOk()->assertJson(['pdf_margin_top' => '0mm']);
});

test('get app version', function () {
    getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJsonStructure([
            'version',
            'channel',
        ]);
});
