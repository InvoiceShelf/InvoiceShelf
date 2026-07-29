<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
