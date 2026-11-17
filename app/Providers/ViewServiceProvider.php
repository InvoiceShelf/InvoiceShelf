<?php

namespace App\Providers;

use App\Support\OAuth2Login;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::share('login_page_logo', get_app_setting('login_page_logo'));
        View::share('login_page_heading', get_app_setting('login_page_heading'));
        View::share('login_page_description', get_app_setting('login_page_description'));
        View::share('admin_page_title', get_app_setting('admin_page_title'));
        View::share('copyright_text', get_app_setting('copyright_text'));

        // A composer rather than View::share, because the session it reads
        // has not started yet while providers boot.
        View::composer('app', function (ViewContract $view): void {
            $view->with('oauth2_login', OAuth2Login::forLoginPage());
        });
    }
}
