<?php

namespace App\Platform\Operations\Http\Company;

use App\Domains\Metadata\Application\CustomFieldModelCatalog;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvoiceShelf\Modules\Registry;

class ConfigController extends Controller
{
    /**
     * Hand the SPA one value out of the application configuration.
     *
     * Exchange-rate drivers and custom-field model types are assembled at
     * runtime rather than read from a config file, so those keys take their
     * own path.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $key = $request->key;

        if ($key === 'exchange_rate_drivers') {
            return response()->json(['exchange_rate_drivers' => $this->exchangeRateDrivers()]);
        }

        if ($key === 'custom_field_models') {
            return response()->json([
                'custom_field_models' => app(CustomFieldModelCatalog::class)->options(),
            ]);
        }

        return response()->json([$key => config('invoiceshelf.'.$key)]);
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
