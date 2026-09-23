<?php

namespace App\Domains\Money\Http\Controllers;

use App\Domains\Money\Application\ExchangeRateLookup;
use App\Domains\Money\Application\ExchangeRateProviderService;
use App\Domains\Money\Contracts\ExchangeRateBackfill;
use App\Domains\Money\ExchangeRates\ExchangeRateException;
use App\Domains\Money\Http\Requests\BulkExchangeRateRequest;
use App\Domains\Money\Http\Requests\ExchangeRateProviderRequest;
use App\Domains\Money\Http\Resources\ExchangeRateProviderResource;
use App\Domains\Money\Models\Currency;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Exchange-rate providers plus everything that reads from them: which codes are
 * already spoken for, what a service supports, the live rate for a document
 * currency, and the one-shot historical backfill.
 *
 * Several lookups below query providers and documents across every company on
 * purpose — that is the behaviour the API has always had and callers depend on
 * it; the company scope is only applied where it already was.
 */
class ExchangeRateProviderController extends Controller
{
    public function __construct(
        private readonly ExchangeRateProviderService $exchangeRateProviderService,
        private readonly ExchangeRateBackfill $exchangeRateBackfill,
        private readonly ExchangeRateLookup $exchangeRateLookup,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $providers = ExchangeRateProvider::whereCompany()->paginate($request->input('limit', 5));

        return ExchangeRateProviderResource::collection($providers);
    }

    public function store(ExchangeRateProviderRequest $request)
    {
        $this->authorize('create', ExchangeRateProvider::class);

        $payload = $request->getExchangeRateProviderPayload();

        $taken = $this->exchangeRateProviderService->checkActiveCurrencies($payload['currencies'] ?? []);

        if ($taken->isNotEmpty()) {
            return respondJson('currency_used', 'Currency used.');
        }

        try {
            // The credentials are proven against the live service before a row exists.
            $this->exchangeRateProviderService->validateProvider($payload);

            return new ExchangeRateProviderResource(
                $this->exchangeRateProviderService->create($payload),
            );
        } catch (ExchangeRateException $exception) {
            return respondJson($exception->errorKey, $exception->getMessage());
        }
    }

    public function show(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('view', $exchangeRateProvider);

        return new ExchangeRateProviderResource($exchangeRateProvider);
    }

    public function update(ExchangeRateProviderRequest $request, ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('update', $exchangeRateProvider);

        $payload = $request->getExchangeRateProviderPayload();

        $taken = $this->exchangeRateProviderService->checkUpdateActiveCurrencies(
            $exchangeRateProvider,
            $payload['currencies'] ?? [],
        );

        if ($taken->isNotEmpty()) {
            return respondJson('currency_used', 'Currency used.');
        }

        try {
            $this->exchangeRateProviderService->validateProvider($payload);
            $this->exchangeRateProviderService->update($exchangeRateProvider, $payload);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        } catch (ExchangeRateException $exception) {
            return respondJson($exception->errorKey, $exception->getMessage());
        }
    }

    public function destroy(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('delete', $exchangeRateProvider);

        // Switching a provider off is a separate, deliberate step before removal.
        if ($exchangeRateProvider->active == true) {
            return respondJson('provider_active', 'Provider Active.');
        }

        $exchangeRateProvider->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function usedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $exclude = $request->input('provider_id');

        $active = ExchangeRateProvider::where('active', true)
            ->whereCompany()
            ->when($exclude, fn ($query) => $query->where('id', '<>', $exclude))
            ->pluck('currencies');

        $all = ExchangeRateProvider::whereCompany()->pluck('currencies');

        return response()->json([
            'allUsedCurrencies' => $this->collectCodes($all),
            'activeUsedCurrencies' => $this->collectCodes($active),
        ]);
    }

    public function supportedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        try {
            $currencies = $this->exchangeRateProviderService->getSupportedCurrencies(
                $request->input('driver'),
                $request->input('key'),
                $request->input('driver_config') ?? [],
            );

            return response()->json(['supportedCurrencies' => $currencies]);
        } catch (ExchangeRateException $exception) {
            return respondJson($exception->errorKey, $exception->getMessage());
        }
    }

    /**
     * Both outcomes are a 200 — the SPA switches on the body, not the status.
     */
    public function activeProvider(Request $request, Currency $currency)
    {
        $covered = ExchangeRateProvider::whereCompany()
            ->where('active', true)
            ->whereJsonContains('currencies', $currency->code)
            ->exists();

        if ($covered) {
            return response()->json([
                'success' => true,
                'message' => 'provider_active',
            ], 200);
        }

        return response()->json([
            'error' => 'no_active_provider',
        ], 200);
    }

    /**
     * Rate from the given currency to the company's base currency: live when a
     * provider covers it, otherwise the newest logged rate, otherwise nothing.
     */
    public function getRate(Request $request, Currency $currency)
    {
        $quote = $this->exchangeRateLookup->quote($currency, $request->header('company'));

        if ($quote !== null) {
            return response()->json(['exchangeRate' => $quote]);
        }

        return response()->json([
            'error' => 'no_exchange_rate_available',
        ], 200);
    }

    public function usedCurrenciesWithoutRate(Request $request)
    {
        $ids = $this->exchangeRateBackfill->currencyIdsMissingRates();

        return response()->json([
            'currencies' => Currency::whereIn('id', $ids)->get(),
        ]);
    }

    public function bulkUpdate(BulkExchangeRateRequest $request)
    {
        $applied = $this->exchangeRateBackfill->apply(
            (int) $request->header('company'),
            $request->validated('currencies'),
        );

        if ($applied) {
            return response()->json([
                'success' => true,
            ]);
        }

        // The backfill has already run for this company; nothing was touched.
        return response()->json([
            'error' => false,
        ]);
    }

    /**
     * Currency codes of every provider in the given set, one entry per provider
     * that covers a code — repeats are meaningful and stay in.
     *
     * @param  Collection<int, mixed>  $providerCurrencies
     * @return array<int, mixed>
     */
    private function collectCodes(Collection $providerCurrencies): array
    {
        return $providerCurrencies
            ->filter(fn ($codes): bool => is_array($codes))
            ->flatten(1)
            ->values()
            ->all();
    }
}
