<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\Company\GetCompanyContextTool;
use App\Platform\Mcp\Tools\McpTool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Silber\Bouncer\BouncerFacade;
use Tests\Support\McpTesting;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->first();
    $this->company = $this->owner->companies()->first();

    $this->tools = collect((fn () => $this->tools)->call(app()->make(InvoiceShelfServer::class, ['transport' => new FakeTransporter])))
        ->mapWithKeys(fn (string $class) => [app($class)->name() => app($class)]);
});

/**
 * @return list<string>
 */
function toolsListedFor(McpContext $context, Collection $tools): array
{
    return $tools->filter(fn (McpTool $tool) => $tool->shouldRegister($context))->keys()->sort()->values()->all();
}

test('every tool but the company context names the permission it needs', function () {
    $context = McpTesting::actAs($this->owner, $this->company->id);

    foreach ($this->tools as $name => $tool) {
        $ability = (fn () => $this->ability($context))->call($tool);

        if ($tool instanceof GetCompanyContextTool) {
            expect($ability)->toBeNull();

            continue;
        }

        expect($ability)->toBeArray("{$name} must name the ability it needs")->toHaveCount(2);
    }
});

test('an owner on a read-only connection sees every read tool', function () {
    $context = McpTesting::actAs($this->owner, $this->company->id, McpConnection::ACCESS_READ);

    expect(toolsListedFor($context, $this->tools))->toBe($this->tools->keys()->sort()->values()->all())
        ->and($this->tools)->toHaveCount(17);
});

test('a member sees only the tools for what their role may view', function () {
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->company->id);
    BouncerFacade::scope()->to($this->company->id);
    $role = BouncerFacade::role()->firstOrCreate(['name' => 'customer-desk', 'title' => 'Customer desk', 'scope' => $this->company->id]);
    BouncerFacade::allow($role)->to('view-customer', Customer::class);
    BouncerFacade::assign('customer-desk')->to($member);
    BouncerFacade::refresh();

    $context = McpTesting::actAs($member, $this->company->id, McpConnection::ACCESS_READ);

    expect(toolsListedFor($context, $this->tools))->toBe([
        'get_company_context',
        'get_customer',
        'rank_top_customers',
        'search_customers',
    ]);
});
