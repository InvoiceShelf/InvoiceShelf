<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PdfTemplateUtils
{
    /**
     * Find the formatted template
     *
     * @param  string  $imageFormat
     * @return array|null
     */
    public static function findFormattedTemplate($templateType, $templateName, $imageFormat = 'base64')
    {
        foreach (array_reverse(self::getFormattedTemplates($templateType, $imageFormat)) as $formattedTemplate) {
            if ($formattedTemplate['name'] === $templateName) {
                return $formattedTemplate;
            }
        }

        return null;
    }

    /**
     * Return the available formatted template paths
     *
     * @param  string  $imageFormat
     * @return array|array[]
     */
    public static function getFormattedTemplates($templateType, $imageFormat = 'base64')
    {

        $files_native = array_map(function ($file) {
            return [
                'path' => $file,
                'custom' => false,
            ];
        }, Storage::disk('views')->files(sprintf('/app/pdf/%s', $templateType)));

        $files_custom = array_map(function ($file) {
            return [
                'path' => $file,
                'custom' => true,
            ];
        }, Storage::disk('pdf_templates')->files(sprintf('/%s', $templateType)));

        $files = array_merge($files_native, $files_custom);
        $files = array_filter($files, function ($file) {
            if (! Str::endsWith($file['path'], '.blade.php')) {
                return false;
            }

            // `{template}_header` / `{template}_footer` are companions rendered
            // alongside their template by the Gotenberg driver, not templates in
            // their own right. Without this they show up in the picker as
            // selectable entries with no preview image.
            return ! Str::endsWith(
                Str::before(basename($file['path']), '.blade.php'),
                ['_header', '_footer']
            );
        });

        $formatted = [];

        foreach ($files as $file) {
            $templateName = Str::before(basename($file['path']), '.blade.php');

            if ($file['custom']) {
                $imagePath = self::getCustomTemplateFilePath($templateType, sprintf('%s.png', $templateName));

                // A custom template needs a same-named .png. Without one the
                // picker used to render <img src=""> — a blank tile with no hint
                // that anything was missing. Fall back to the preview of the
                // template make:template clones from.
                if (! File::exists($imagePath)) {
                    $imagePath = resource_path("static/img/PDF/{$templateType}1.png");
                }
            } else {
                $imagePath = resource_path('static/img/PDF/'.$templateName.'.png');
            }

            if (empty($imageFormat)) {
                $imageValue = '';
            } elseif ($imageFormat == 'path') {
                $imageValue = $imagePath;
            } else {
                $imageValue = File::exists($imagePath) ? ImageUtils::toBase64Src($imagePath) : '';
            }

            // Keyed by name so a custom template that shares a built-in's name
            // appears once rather than twice. Custom entries come last and so
            // win, which matches what findFormattedTemplate() already resolved
            // to — the picker just used to show both tiles with no way to tell
            // which one you were clicking.
            $formatted[$templateName] = [
                'name' => $templateName,
                'path' => $imageValue,
                'custom' => $file['custom'],
            ];
        }

        return array_values($formatted);
    }

    /**
     * The view to render for a document, preferring a custom override.
     *
     * Invoices and estimates let you pick between several designs, so their
     * template is chosen per document and $fallback is the design to use when
     * that choice cannot be honoured. Payment receipts and reports have no
     * chooser and no design to pick, so overriding one means dropping a
     * same-named file into storage/app/templates/pdf/{type}/ and having it win.
     *
     * The fallback matters because the stored name is not trustworthy. It is
     * validated when a document is saved through the UI, but seeders, imports,
     * recurring-invoice copies and rows predating that validation all bypass it
     * — and an unresolvable name used to reach `$template['custom']` on null and
     * take the whole PDF route down with a 500.
     */
    public static function resolveView(string $templateType, ?string $templateName, ?string $fallback = null): string
    {
        foreach (array_filter([$templateName, $fallback]) as $candidate) {
            // View::exists rather than a disk check: the namespace is what
            // actually renders, so asking it directly means the two cannot
            // disagree about where custom templates live.
            foreach ([
                sprintf('pdf_templates::%s.%s', $templateType, $candidate),
                sprintf('app.pdf.%s.%s', $templateType, $candidate),
            ] as $view) {
                if (View::exists($view)) {
                    if ($candidate !== $templateName) {
                        Log::warning('PDF template not found, falling back.', [
                            'type' => $templateType,
                            'requested' => $templateName,
                            'used' => $candidate,
                        ]);
                    }

                    return $view;
                }
            }
        }

        // Nothing resolved. Name the built-in path so the failure points at
        // something real rather than at whatever was stored.
        return sprintf('app.pdf.%s.%s', $templateType, $fallback ?? $templateName);
    }

    /**
     * Returns custom template path
     *
     * @param  string  $fileName
     */
    public static function getCustomTemplateFilePath($templateType, $fileName = ''): string
    {
        $path = ! empty($fileName) ? sprintf('/%s/%s', $templateType, $fileName) : sprintf('/%s/', $templateType);

        return Storage::disk('pdf_templates')->path($path);
    }

    /**
     * Check if custom template exists.
     *
     * @param  $templateName
     * @return string
     */
    public static function customTemplateFileExists($templateType, $fileName)
    {
        return Storage::disk('pdf_templates')->exists(sprintf('/%s/%s', $templateType, $fileName));
    }

    /**
     * Save template markup file
     *
     * @return bool|string
     */
    public static function toCustomTemplateMarkupFile($contents, $templateType, $templateName)
    {
        return self::toCustomTemplateFile($contents, $templateType, $templateName.'.blade.php');
    }

    /**
     * Save template image file
     *
     *
     * @return bool|string
     */
    public static function toCustomTemplateImageFile($contents, $templateType, $templateName, $imageType = 'png')
    {
        return self::toCustomTemplateFile($contents, $templateType, $templateName.'.'.$imageType);
    }

    /**
     * Save file contents into a template file of specific template type.
     *
     *
     * @return bool|string
     */
    public static function toCustomTemplateFile($contents, $templateType, $fileName)
    {
        return Storage::disk('pdf_templates')->put(
            sprintf('/%s/%s', $templateType, $fileName),
            $contents
        );
    }
}
