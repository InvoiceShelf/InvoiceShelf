<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Paper size was a Gotenberg-only setting stored as one "210mm 297mm" string.
 * It is now driver-neutral and split across pdf_paper_width / pdf_paper_height,
 * so dompdf can honour it too.
 *
 * This exists because the old key ships in 2.x, not merely in a 3.x alpha: a
 * stable install that deliberately chose Letter would otherwise come back up on
 * A4 after upgrading. The format is unchanged between the two lines, so the
 * split is a straight parse.
 *
 * Also drops gotenberg_margins, which 2.x validates, saves and copies into
 * config on every boot but never reads -- both drivers hardcoded their margins.
 * Nobody has been getting an effect from it, so there is nothing to carry
 * forward and the new margin settings start from their defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        $papersize = Setting::getSetting('gotenberg_papersize');

        if (is_string($papersize) && preg_match('/^(\S+)\s+(\S+)$/', trim($papersize), $m)) {
            Setting::setSettings([
                'pdf_paper_width' => $m[1],
                'pdf_paper_height' => $m[2],
            ]);
        }

        Setting::whereIn('option', ['gotenberg_papersize', 'gotenberg_margins'])->delete();
    }

    public function down(): void
    {
        $width = Setting::getSetting('pdf_paper_width');
        $height = Setting::getSetting('pdf_paper_height');

        if ($width && $height) {
            Setting::setSetting('gotenberg_papersize', "{$width} {$height}");
        }

        Setting::whereIn('option', [
            'pdf_paper_width',
            'pdf_paper_height',
            'pdf_orientation',
            'pdf_margin_top',
            'pdf_margin_right',
            'pdf_margin_bottom',
            'pdf_margin_left',
        ])->delete();
    }
};
