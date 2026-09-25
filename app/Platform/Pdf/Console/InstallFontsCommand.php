<?php

namespace App\Platform\Pdf\Console;

use App\Platform\Pdf\Application\FontService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Downloads font packages ahead of time, each file checked against its
 * sha256.
 *
 * An image bakes them in with `--all --path=<dir under the app>`, then runs
 * with PDF_FONTS_PATH pointing there and PDF_FONTS_DOWNLOAD=false, so no
 * document ever waits on a download and a read-only filesystem is enough.
 * Runs whether or not run-time downloads are allowed: it is the explicit step.
 */
class InstallFontsCommand extends Command
{
    protected $signature = 'pdf:fonts:install
        {packages?* : Packages to install, e.g. noto-sans-jp}
        {--all : Install every package}
        {--path= : Directory to install into (default storage/fonts)}';

    protected $description = 'Download PDF font packages ahead of time, checked against their checksums';

    public function handle(FontService $fonts): int
    {
        $known = array_filter($fonts->packages(), fn (array $package): bool => empty($package['bundled']));
        $names = $this->option('all') ? array_keys($known) : (array) $this->argument('packages');

        if ($names === []) {
            $this->components->error('Name the packages to install, or pass --all. Known: '.implode(', ', array_keys($known)).'.');

            return self::FAILURE;
        }

        $unknown = array_diff($names, array_keys($known));

        if ($unknown !== []) {
            $this->components->error('Unknown font package: '.implode(', ', $unknown).'.');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('fonts');
        $failed = false;

        foreach ($names as $name) {
            try {
                $this->components->task($known[$name]['name'], fn () => $fonts->downloadPackage($known[$name], $directory));
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
