<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Tools\McpTool;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Silber\Bouncer\BouncerFacade;
use Tests\Support\McpTesting;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->first();
    $this->company = $this->owner->companies()->first();

    $this->writingTool = new class extends McpTool
    {
        protected function writes(): bool
        {
            return true;
        }

        protected function ability(): ?array
        {
            return ['create', Invoice::class];
        }

        public function handle(Request $request): Response
        {
            return Response::text('done');
        }
    };
});

test('a tool that writes is hidden from a read-only connection', function () {
    $read = McpTesting::actAs($this->owner, $this->company->id, McpConnection::ACCESS_READ);

    expect($this->writingTool->shouldRegister($read))->toBeFalse();
});

test('a tool is listed when the connection writes and the role allows it', function () {
    $write = McpTesting::actAs($this->owner, $this->company->id, McpConnection::ACCESS_WRITE);

    expect($this->writingTool->shouldRegister($write))->toBeTrue();
});

test('a tool is hidden from a member whose role lacks the ability', function () {
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->company->id);
    BouncerFacade::scope()->to($this->company->id);
    BouncerFacade::role()->firstOrCreate(['name' => 'viewer', 'title' => 'Viewer', 'scope' => $this->company->id]);
    BouncerFacade::assign('viewer')->to($member);

    $context = McpTesting::actAs($member, $this->company->id, McpConnection::ACCESS_WRITE);

    expect($this->writingTool->shouldRegister($context))->toBeFalse();
});
