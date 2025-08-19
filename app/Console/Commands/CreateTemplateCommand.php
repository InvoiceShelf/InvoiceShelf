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
    protected $signature = 'make:template
                            {--N|name= : Template Name e.g. standard}
                            {--T|type= : Template Type [invoice | estimate]}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create estimate or invoice pdf template.';

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
     *
     * @return int
     */
    public function handle()
    {
        /**
         * Template Name
         *
         * @var array
         */
        $name = $this->getNameOption();
        /**
         * Template Type
         *
         * @var string
         */
        $type = $this->getTypeOption();

        if ($this->isTemplateNew($type, $name)) {
            $this->info('- Template with given name already exists.');

            return self::INVALID;
        }

        if (! $this->cloneTemplate($type, $name)) {
            $this->error(
                sprintf('Unable to create %s template.', ucfirst($type))
            );
            $this->info('make sure template name is free of special '
                . 'characters.');

            return self::FAILURE;
        }

        if (! $this->cloneTemplateThumbnail($type, $name)) {
            $this->error(sprintf(
                'Unable to clone %s template thumbnail from default template.',
                ucfirst($type)
            ));

            return self::FAILURE;
        }

        if ($this->templateHasPartials($type, 'partials/table.blade.php')) {
            if (! $this->cloneTemplatePartials(
                $type,
                'partials/table.blade.php'
            )) {
                $this->error(sprintf(
                    'Unable to clone %s template partials from default'
                        . ' template.',
                    ucfirst($type)
                ));

                return self::FAILURE;
            }
        }

        $this->info(
            sprintf('%s Template created successfully', ucfirst($type))
        );
        $this->info(sprintf(
            'you will find it at in: %s',
            PdfTemplateUtils::getCustomTemplateFilePath(
                $type,
                sprintf('%s.blade.php', $name)
            )
        ));

        return self::SUCCESS;
    }

    /**
     * Check if option is valid type
     *
     * @param  array|string|bool|null  $option  command option
     * @return bool
     */
    protected function validOption($option)
    {
        return ! is_bool($option)
            && ! is_array($option)
            && ! is_null($option)
            && ! empty($option);
    }

    /**
     * Get template name
     *
     * @return string
     */
    protected function getNameOption()
    {
        $name = strtolower($this->option('name'));

        if ($this->validOption($name)) {
            return $name;
        }

        $this->error('Template name missing or invalid.');

        $label = 'What would you like to name the template?';

        // TODO: User Input needs to be properly sanitised.
        return strval($this->ask($label));
    }

    /**
     * Get template type
     *
     * @return string
     */
    protected function getTypeOption()
    {
        $type = strtolower($this->option('type'));
        $choice = [
            strtolower(PdfTemplateUtils::INVOICE),
            strtolower(PdfTemplateUtils::ESTIMATE),
        ];

        if ($this->validOption($type) && in_array($type, $choice)) {
            return $type;
        }

        $this->error('Template type missing or invalid.');

        $label = 'Create a template for?';

        return $this->choice($label, $choice);
    }

    /**
     * Check if Template is new
     *
     * @param  string  $type  Template Type
     * @param  string  $name  Template Name
     * @return bool
     */
    protected function isTemplateNew($type, $name)
    {
        $this->info(
            sprintf('Checking if %s template already exists', ucfirst($type))
        );

        return PdfTemplateUtils::customTemplateFileExists(
            $type,
            sprintf('%s.blade.php', $name)
        );
    }

    /**
     * Clone Template Type
     *
     * @param  string  $type  Template Type
     * @param  string  $name  Template Name
     * @return bool Operation Success Status
     */
    protected function cloneTemplate($type, $name)
    {
        $this->info(sprintf('Cloning default %s template', ucfirst($type)));

        /**
         * Default template path
         *
         * @var string
         */
        $oldPath = sprintf('app.pdf.%s', $type);
        /**
         * New template path
         *
         * @var string
         */
        $newPath = sprintf('pdf_templates::%s', $type);
        /**
         * Default template contents
         *
         * @var string
         */
        $defaultTemplate = Storage::disk('views')->get(
            "/app/pdf/{$type}/{$type}1.blade.php"
        );
        /**
         * New template contents with all references to old partials @include
         * paths to new location
         *
         * @var string
         */
        $content = Str::replace($oldPath, $newPath, $defaultTemplate);

        return PdfTemplateUtils::toCustomTemplateMarkupFile(
            $content,
            $type,
            $name
        );
    }

    /**
     * Clone Template Thumbnail
     *
     * @param  string  $type  Template Type
     * @param  string  $name  Template Name
     * @return bool Operation Success Status
     */
    protected function cloneTemplateThumbnail($type, $name)
    {
        $this->info(
            sprintf('Cloning default %s template thumbnail', ucfirst($type))
        );

        /**
         * Default template thumbnail contents
         *
         * @var string
         */
        $defaultTemplateThumbContents = File::get(
            resource_path("static/img/PDF/{$type}1.png")
        );

        return PdfTemplateUtils::toCustomTemplateImageFile(
            $defaultTemplateThumbContents,
            $type,
            $name,
        );
    }

    /**
     * Check if template type has partials
     *
     * @param  string  $type  Template Type
     * @param  string  $partialPath  Template partial path
     * @return bool has partials
     */
    protected function templateHasPartials($type, $partialPath)
    {
        $this->info(
            sprintf('Checking if %s template has partials', ucfirst($type))
        );

        return Storage::disk('views')
            ->exists(sprintf('app/pdf/%s/%s', $type, $partialPath));
    }

    /**
     * Check if template type has partials
     *
     * @param  string  $type  Template Type
     * @param  string  $partialPath  Template partial path
     * @return bool Operation Success Status
     */
    protected function cloneTemplatePartials($type, $partialPath)
    {
        $this->info(
            sprintf(
                '- %s Template has partials, cloning them as well',
                ucfirst($type)
            )
        );

        /**
         * Default template thumbnail contents
         *
         * @var string
         */
        $defaultTemplatePartialContents = Storage::disk('views')->get(
            "/app/pdf/{$type}/{$partialPath}"
        );

        return PdfTemplateUtils::toCustomTemplateFile(
            $defaultTemplatePartialContents,
            $type,
            $partialPath
        );
    }
}
