<?php

namespace App\Console\Commands;

use App\Space\PdfTemplateUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateTemplateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:template {name} {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create estimate or invoice pdf template.                               ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $templateName = $this->argument('name');
        $templateType = $this->option('type');

        if (! $templateType) {
            $templateType = $this->choice('Create a template for?', ['invoice', 'estimate']);
        }

        if (PdfTemplateUtils::customTemplateFileExists($templateType, sprintf('%s.blade.php', $templateName))) {
            $this->info('Template with given name already exists.');

            return self::INVALID;
        }

        if (! PdfTemplateUtils::toCustomTemplateMarkupFile(
            Str::replace(
                sprintf('app.pdf.%s', $templateType),
                sprintf('pdf_templates::%s', $templateType),
                Storage::disk('views')->get("/app/pdf/{$templateType}/{$templateType}1.blade.php"),
            ),
            $templateType,
            $templateName
        )) {
            $this->error(sprintf('Unable to create %s template.', ucfirst($templateType)));

            return self::FAILURE;
        }

        PdfTemplateUtils::toCustomTemplateImageFile(
            File::get(resource_path("static/img/PDF/{$templateType}1.png")),
            $templateType,
            $templateName,
        );

        if (! PdfTemplateUtils::customTemplateFileExists($templateType, 'partials/table.blade.php')) {
            PdfTemplateUtils::toCustomTemplateFile(
                Storage::disk('views')->get("/app/pdf/{$templateType}/partials/table.blade.php"),
                $templateType,
                'partials/table.blade.php'
            );
        }

        $this->info(
            sprintf('%s Template created successfully at %s',
                ucfirst($templateType),
                PdfTemplateUtils::getCustomTemplateFilePath($templateType, sprintf('%s.blade.php', $templateName))
            )
        );

        return self::SUCCESS;
    }
}
