<?php

namespace InvoiceShelf\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Bouncer\Scopes\DefaultScope;
use InvoiceShelf\Space\InstallUtils;
use Silber\Bouncer\Database\Models as BouncerModels;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapThree();

        if (InstallUtils::isDbCreated()) {
            $this->addMenus();
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        BouncerModels::scope(new DefaultScope);
    }

    public function addMenus()
    {
        //main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('invoiceshelf.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        //setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('invoiceshelf.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('invoiceshelf.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group']);
    }
}
