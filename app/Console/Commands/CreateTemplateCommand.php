<?php

namespace App\Console\Commands;

use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateTemplateCommand extends Command
{
    /**
     * Document types that can be cloned. The --type option is checked against
     * this rather than only the interactive prompt: passing an unsupported one
     * used to skip the prompt and die on an uncaught FileNotFoundException
     * further down, with a stack trace instead of a message.
     */
    private const TYPES = ['invoice', 'estimate'];

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
    protected $description = 'Create estimate or invoice pdf template.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $templateName = $this->argument('name');
        $templateType = $this->option('type');

        if (! $templateType) {
            $templateType = $this->choice('Create a template for?', self::TYPES);
        }

        if (! in_array($templateType, self::TYPES, true)) {
            $this->error(sprintf(
                'Unsupported template type "%s". Supported types: %s.',
                $templateType,
                implode(', ', self::TYPES)
            ));

            return self::INVALID;
        }

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $templateName)) {
            $this->error('Template name may only contain letters, numbers, dots, dashes and underscores.');

            return self::INVALID;
        }

        if (PdfTemplateUtils::customTemplateFileExists($templateType, sprintf('%s.blade.php', $templateName))) {
            $this->info('Template with given name already exists.');

            return self::INVALID;
        }

        $source = Storage::disk('views')->get("/app/pdf/{$templateType}/{$templateType}1.blade.php");

        // Point this template at its own copy of the shared partial before the
        // blanket namespace rewrite below catches it. Previously every custom
        // template of a type included the same partials/table.blade.php, which
        // was written once and then reused, so editing the table for one custom
        // template silently changed it for all of them.
        $source = Str::replace(
            sprintf('app.pdf.%s.partials.table', $templateType),
            sprintf('pdf_templates::%s.partials.%s.table', $templateType, $templateName),
            $source,
        );

        $source = Str::replace(
            sprintf('app.pdf.%s', $templateType),
            sprintf('pdf_templates::%s', $templateType),
            $source,
        );

        if (! PdfTemplateUtils::toCustomTemplateMarkupFile($source, $templateType, $templateName)) {
            $this->error(sprintf('Unable to create %s template.', ucfirst($templateType)));

            return self::FAILURE;
        }

        PdfTemplateUtils::toCustomTemplateImageFile(
            File::get(resource_path("static/img/PDF/{$templateType}1.png")),
            $templateType,
            $templateName,
        );

        PdfTemplateUtils::toCustomTemplateFile(
            Storage::disk('views')->get("/app/pdf/{$templateType}/partials/table.blade.php"),
            $templateType,
            sprintf('partials/%s/table.blade.php', $templateName),
        );

        // Repeating page header/footer, if the source template has one. Named
        // with the {template}_header / {template}_footer suffix the Gotenberg
        // driver looks for.
        foreach (['_header', '_footer'] as $suffix) {
            $companion = "/app/pdf/{$templateType}/{$templateType}1{$suffix}.blade.php";

            if (Storage::disk('views')->exists($companion)) {
                PdfTemplateUtils::toCustomTemplateFile(
                    Storage::disk('views')->get($companion),
                    $templateType,
                    sprintf('%s%s.blade.php', $templateName, $suffix),
                );
            }
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
