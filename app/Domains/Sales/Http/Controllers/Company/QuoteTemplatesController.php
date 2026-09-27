<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Models\Quote;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Http\Request;

class QuoteTemplatesController extends Controller
{
    /**
     * List the PDF templates a quote can be rendered with, each already
     * paired with its preview image.
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Quote::class);

        return response()->json([
            'quoteTemplates' => PdfTemplateUtils::getFormattedTemplates('quote'),
        ]);
    }
}
