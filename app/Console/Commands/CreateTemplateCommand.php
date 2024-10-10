<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
        $type = $this->option('type');

        if (! $type) {
            $type = $this->choice('Create a template for?', ['invoice', 'estimate']);
        }

        $templatePath = "views/app/pdf/{$type}/{$templateName}.blade.php";
        $imagePath = "static/img/PDF/{$templateName}.png";

        $disk = Storage::build([
            'driver' => 'local',
            'root' => resource_path(),
        ]);

        if ($disk->exists($templatePath)) {
            $this->error("Template with given name already exists.");

            return 1;
        }

        $toCopy = [
            "views/app/pdf/{$type}/{$type}1.blade.php" => $templatePath,
            "static/img/PDF/{$type}1.png" => $imagePath,
        ];

        $success = true;
        foreach ($toCopy as $from => $to) {
            $success = $success && $disk->copy($from, $to);
        }

        if ($success === false) {
            $disk->delete($toCopy);

            $this->error('Failed to copy templates files');

            return 1;
        }

        $process = new Process(['npm', 'run', 'build']);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (!$process->isSuccessful()) {
            $disk->delete($toCopy);

            throw new ProcessFailedException($process);
        }

        $path = $disk->path($templatePath);
        $type = ucfirst($type);
        $this->info("{$type} Template created successfully at {$path}");

        return 0;
    }
}
