<?php

namespace App\Platform\Pdf\Console;

use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Scaffolds a custom PDF template by cloning one of the designs the
 * application ships, along with the partials and the repeating
 * header/footer that come with it.
 *
 * Whether the name is yours to choose or has to match the document being
 * replaced depends on the type; the two constants below spell that out.
 */
class CreateTemplateCommand extends Command
{
    /**
     * Types you pick a design for. A custom template is a new, separately
     * selectable entry in the picker, so the name is yours to choose and the
     * clone source is the first built-in.
     *
     * The --type option is checked against these rather than only the
     * interactive prompt: passing an unsupported one used to skip the prompt and
     * die on an uncaught FileNotFoundException further down, with a stack trace
     * instead of a message.
     */
    private const SELECTABLE_TYPES = ['invoice', 'estimate', 'quote'];

    /**
     * Types with no picker. A custom template here replaces the built-in outright
     * (see PdfTemplateUtils::resolveView), so the name is not free: it has to
     * match the document you are overriding.
     */
    private const OVERRIDE_TYPES = [
        'payment' => ['payment'],
        'reports' => ['expenses', 'profit-loss', 'sales-customers', 'sales-items', 'tax-summary'],
    ];

    private static function types(): array
    {
        return array_merge(self::SELECTABLE_TYPES, array_keys(self::OVERRIDE_TYPES));
    }

    protected $signature = 'make:template {name} {--type=}';

    protected $description = 'Create estimate or invoice pdf template.';

    /**
     * Clone a shipped design into the custom-template area, where it can be
     * edited without disturbing the one the application ships.
     */
    public function handle(): int
    {
        $templateName = $this->argument('name');
        $templateType = $this->option('type');

        if (! $templateType) {
            $templateType = $this->choice('Create a template for?', self::types());
        }

        if (! in_array($templateType, self::types(), true)) {
            $this->error(sprintf(
                'Unsupported template type "%s". Supported types: %s.',
                $templateType,
                implode(', ', self::types())
            ));

            return self::INVALID;
        }

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $templateName)) {
            $this->error('Template name may only contain letters, numbers, dots, dashes and underscores.');

            return self::INVALID;
        }

        $isOverride = array_key_exists($templateType, self::OVERRIDE_TYPES);

        if ($isOverride && ! in_array($templateName, self::OVERRIDE_TYPES[$templateType], true)) {
            $this->error(sprintf(
                '"%s" is not a %s document. An override replaces a specific one, so the name must be one of: %s.',
                $templateName,
                $templateType,
                implode(', ', self::OVERRIDE_TYPES[$templateType])
            ));

            return self::INVALID;
        }

        if (PdfTemplateUtils::customTemplateFileExists($templateType, sprintf('%s.blade.php', $templateName))) {
            $this->info('Template with given name already exists.');

            return self::INVALID;
        }

        // An override clones the document it replaces; a selectable template
        // clones the first built-in design.
        $sourceType = $templateType === 'quote' ? 'estimate' : $templateType;
        $sourceName = $isOverride ? $templateName : "{$sourceType}1";

        $source = Storage::disk('views')->get("/app/pdf/{$sourceType}/{$sourceName}.blade.php");

        // Point this template at its own copies of the partials its type ships
        // before the blanket namespace rewrite below catches them. Previously
        // every custom template of a type included the same
        // partials/table.blade.php, which was written once and then reused, so
        // editing the table for one custom template silently changed it for all
        // of them.
        $source = PdfTemplateUtils::rewriteViewReferences(
            $source,
            PdfTemplateUtils::partialViewMap($templateType, $templateName),
        );

        $source = Str::replace(
            sprintf('app.pdf.%s', $sourceType),
            sprintf('pdf_templates::%s', $templateType),
            $source,
        );

        if (! PdfTemplateUtils::toCustomTemplateMarkupFile($source, $templateType, $templateName)) {
            $this->error(sprintf('Unable to create %s template.', ucfirst($templateType)));

            return self::FAILURE;
        }

        // Only selectable types need a preview: an override replaces one
        // document outright and never appears in a picker.
        if (! $isOverride) {
            PdfTemplateUtils::toCustomTemplateImageFile(
                File::get(resource_path("static/img/PDF/{$templateType}1.png")),
                $templateType,
                $templateName,
            );
        }

        // Every partial of the type, not just the table: reports extend a shared
        // layout which in turn includes a shared stylesheet, and a clone that
        // was rewritten to the custom namespace without them pointed at views
        // that did not exist and failed to render.
        PdfTemplateUtils::copyTemplatePartials($templateType, $templateName);

        // Repeating page header/footer, if the source template has one. Named
        // with the {template}_header / {template}_footer suffix the Gotenberg
        // driver looks for.
        foreach (['_header', '_footer'] as $suffix) {
            $companion = "/app/pdf/{$sourceType}/{$sourceName}{$suffix}.blade.php";

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
