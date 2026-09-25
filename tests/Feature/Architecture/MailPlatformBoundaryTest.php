<?php

use App\Platform\Mail\Application\EloquentEmailLogWriter;
use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Mail\Contracts\EmailLogWriter;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Mail\Http\Admin\MailConfigurationController;
use App\Platform\Mail\Http\Company\CompanyMailConfigurationController;
use App\Platform\Mail\MailServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the mail platform owns configuration and authorization', function () {
    expect(app()->getProviders(MailServiceProvider::class))->toHaveCount(1)
        ->and(app(MailConfigurator::class))->toBeInstanceOf(MailConfigurationService::class)
        ->and(app(EmailLogWriter::class))->toBeInstanceOf(EloquentEmailLogWriter::class)
        ->and(Gate::has('manage email config'))->toBeTrue();

    expect(class_exists('App\\Services\\Mail\\MailConfigurationService'))->toBeFalse()
        ->and(class_exists('App\\Services\\Mail\\CompanyMailConfigService'))->toBeFalse()
        ->and(class_exists('App\\Mail\\TestMail'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\Settings\\MailConfigurationController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Settings\\CompanyMailConfigurationController'))->toBeFalse();
});

test('the mail platform preserves its public configuration routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('#^api/v1/(?:company/)?mail/#', $route->uri()) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'GET|HEAD api/v1/company/mail/company-config',
        'GET|HEAD api/v1/company/mail/config',
        'GET|HEAD api/v1/company/mail/drivers',
        'GET|HEAD api/v1/mail/config',
        'GET|HEAD api/v1/mail/drivers',
        'POST api/v1/company/mail/company-config',
        'POST api/v1/company/mail/company-test',
        'POST api/v1/mail/config',
        'POST api/v1/mail/test',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())->toStartWith('App\\Platform\\Mail\\Http\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }

    expect($routes->get('GET|HEAD api/v1/mail/config')->getActionName())
        ->toBe(MailConfigurationController::class.'@getMailEnvironment')
        ->and($routes->get('GET|HEAD api/v1/company/mail/config')->getActionName())
        ->toBe(CompanyMailConfigurationController::class.'@getDefaultConfig');
});
