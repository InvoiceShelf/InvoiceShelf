<?php

namespace App\Domains\Money\Http\Controllers\Admin;

use App\Domains\Money\Application\CurrencyService;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Money\Models\Currency;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The currency list as an installation-wide concern.
 *
 * Currencies are shared by every company, so this sits behind `super-admin`
 * rather than a company's own `manage settings`: refreshing the list changes
 * what every company on the installation can invoice in.
 *
 * The refresh exists so that adding a currency stops costing a migration.
 * Before it, a currency contributed to the seeder reached fresh installations
 * only, and the 2.x line grew one migration per currency to make up for it.
 */
class CurrenciesController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(
            $this->currencies->getAllWithCommonFirst(),
        );
    }

    /**
     * Plant anything the shipped catalogue has and this installation does not.
     */
    public function refresh(): JsonResponse
    {
        $result = $this->currencies->sync();

        return response()->json([
            'added' => $result['added'],
            'updated' => $result['updated'],
            'total' => Currency::query()->count(),
        ]);
    }
}
