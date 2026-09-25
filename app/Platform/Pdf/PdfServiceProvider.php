<?php

namespace App\Platform\Pdf;

use App\Platform\Pdf\Application\PdfConfigurationService;
use App\Platform\Pdf\Console\ComparePdfDriversCommand;
use App\Platform\Pdf\Console\CreateTemplateCommand;
use App\Platform\Pdf\Console\InstallFontsCommand;
use App\Platform\Pdf\Contracts\PdfConfigurator;
use App\Platform\Pdf\Policies\PdfAccessPolicy;
use App\Platform\Pdf\Rendering\PdfService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('pdf.driver', PdfService::class);
        $this->app->singleton(PdfConfigurationService::class);
        $this->app->bind(
            PdfConfigurator::class,
            fn (Application $app): PdfConfigurationService => $app->make(PdfConfigurationService::class),
        );
    }

    public function boot(): void
    {
        Gate::define('manage pdf config', [PdfAccessPolicy::class, 'manageConfiguration']);
        View::addNamespace('pdf_templates', storage_path('app/templates/pdf'));

        $this->commands([
            ComparePdfDriversCommand::class,
            CreateTemplateCommand::class,
            InstallFontsCommand::class,
        ]);
    }
}
