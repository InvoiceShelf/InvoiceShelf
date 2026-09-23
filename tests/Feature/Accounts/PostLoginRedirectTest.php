<?php

use App\Domains\Accounts\Application\PostLoginRedirect;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Route;

test('a same-origin path is accepted', function (string $next) {
    expect(PostLoginRedirect::sanitize($next))->toBe($next);
})->with([
    '/admin/dashboard',
    '/admin/invoices?page=2',
    '/oauth/authorize?client_id=abc&redirect_uri=https%3A%2F%2Fclaude.ai%2Fcallback',
]);

test('anything that could leave the origin is refused', function (mixed $next) {
    expect(PostLoginRedirect::sanitize($next))->toBeNull();
})->with([
    'protocol-relative' => '//evil.example/path',
    'backslash' => '/\\evil.example',
    'absolute' => 'https://evil.example/',
    'scheme only' => 'javascript:alert(1)',
    'relative' => 'admin/dashboard',
    'control character' => "/admin\n/dashboard",
    'empty' => '',
    'array' => [['/admin']],
    'null' => null,
]);

test('only listed pages count as server-rendered', function () {
    expect(PostLoginRedirect::isServerPath('/oauth/authorize'))->toBeTrue()
        ->and(PostLoginRedirect::isServerPath('/oauth/authorize?client_id=1'))->toBeTrue()
        ->and(PostLoginRedirect::isServerPath('/oauth/authorize-evil'))->toBeFalse()
        ->and(PostLoginRedirect::isServerPath('/oauth/token'))->toBeFalse()
        ->and(PostLoginRedirect::isServerPath('/admin/dashboard'))->toBeFalse();
});

// The sign-in pages also carry the `install` middleware, whose probe is cached
// per process before the test schema exists, so the `guest` middleware is
// exercised on a route of its own.
beforeEach(function () {
    Route::middleware('guest')->get('/_guest-test', fn () => 'sign-in page');
});

test('a guest reaches a sign-in page', function () {
    $this->get('/_guest-test')->assertOk()->assertSee('sign-in page');
});

test('a signed-in visitor to a sign-in page goes on to a safe next page', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->get('/_guest-test?next='.urlencode('/admin/invoices'))->assertRedirect('/admin/invoices');
});

test('a signed-in visitor with an unsafe next page goes to the dashboard', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->get('/_guest-test?next='.urlencode('//evil.example'))->assertRedirect('/admin/dashboard');
});
