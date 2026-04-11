<?php

namespace App\Http\Controllers\Company\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvoiceShelf\Modules\Registry;

class ConfigController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->key === 'exchange_rate_drivers') {
            return response()->json([
                'exchange_rate_drivers' => $this->exchangeRateDrivers(),
            ]);
        }

        return response()->json([
            $request->key => config('invoiceshelf.'.$request->key),
        ]);
    }

    /**
     * Build the exchange rate driver list from the module Registry.
     *
     * Returns enriched objects (with label, website, and config_fields) so the
     * frontend can render driver-specific configuration forms without hardcoding
     * any per-driver UI.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function exchangeRateDrivers(): array
    {
        return collect(Registry::allDrivers('exchange_rate'))
            ->map(fn (array $meta, string $name) => [
                'value' => $name,
                'label' => $meta['label'] ?? $name,
                'website' => $meta['website'] ?? '',
                'config_fields' => $meta['config_fields'] ?? [],
            ])
            ->values()
            ->all();
    }
}
