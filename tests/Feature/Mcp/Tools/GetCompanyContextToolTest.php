<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Application\SerialNumberService;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\Company\GetCompanyContextTool;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\McpTesting;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::where('role', 'super admin')->first();
    $this->company = $this->user->companies()->first();
});

test('it describes the bound company and the connection', function () {
    McpTesting::actAs($this->user, $this->company->id, McpConnection::ACCESS_READ);

    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetCompanyContextTool::class)
        ->assertOk()
        ->assertStructuredContent(function ($content) {
            $content->where('company.id', $this->company->id)
                ->where('company.name', $this->company->name)
                ->where('connection.access', 'read')
                ->has('currency.code')
                ->has('tax_setup.taxes_per_line')
                ->has('sales_tax_types')
                ->has('payment_methods')
                ->has('units')
                ->has('expense_categories')
                ->has('templates.invoice')
                ->has('custom_fields')
                ->has('next_numbers.invoice')
                ->has('next_numbers.estimate')
                ->has('next_numbers.payment')
                ->etc();
        });
});

test('it previews the next invoice number from the invoice sequence alone', function () {
    McpTesting::actAs($this->user, $this->company->id, McpConnection::ACCESS_READ);

    $expected = (new SerialNumberService)
        ->setCompany($this->company->id)
        ->setModel(new Invoice)
        ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
        ->setModelObject(null)
        ->getNextNumber();

    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetCompanyContextTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($content) => $content->where('next_numbers.invoice', $expected)->etc());
});

test('it is read-only and closed-world', function () {
    $tool = (new GetCompanyContextTool)->toArray();

    expect($tool['name'])->toBe('get_company_context')
        ->and($tool['annotations'])->toBe([
            'readOnlyHint' => true,
            'idempotentHint' => true,
            'destructiveHint' => false,
            'openWorldHint' => false,
        ]);
});
