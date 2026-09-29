<?php

use App\Platform\Notifications\AppNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => purchaseFixtures($this));

afterEach(fn () => Carbon::setTestNow());

test('bills falling due and overdue are each told once however often the sweep runs', function () {
    Carbon::setTestNow('2026-09-28 09:00:00');
    Notification::fake();
    $soon = $this->postJson('/api/v1/bills', purchaseBillPayload($this))->assertSuccessful()->json('data.id');
    $late = $this->postJson('/api/v1/bills', [...purchaseBillPayload($this), 'due_date' => '2026-09-20'])->assertSuccessful()->json('data.id');
    $this->postJson('/api/v1/bills', [...purchaseBillPayload($this), 'due_date' => '2026-12-31'])->assertSuccessful();

    $this->artisan('bills:check-due')->assertSuccessful();
    $this->artisan('bills:check-due')->assertSuccessful();

    $sent = Notification::sent($this->user, AppNotification::class);
    expect($sent->filter(fn ($n) => $n->message->type === 'bill_due_soon')->map(fn ($n) => $n->message->subject->id)->values()->all())->toBe([$soon])
        ->and($sent->filter(fn ($n) => $n->message->type === 'bill_overdue')->map(fn ($n) => $n->message->subject->id)->values()->all())->toBe([$late]);
});
