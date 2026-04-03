<?php

namespace App\Http\Controllers\Company\ExchangeRate;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkExchangeRateRequest;
use App\Http\Requests\ExchangeRateProviderRequest;
use App\Http\Resources\ExchangeRateProviderResource;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Estimate;
use App\Models\ExchangeRateLog;
use App\Models\ExchangeRateProvider;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tax;
use App\Services\ExchangeRateProviderService;
use App\Traits\ExchangeRateProvidersTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ExchangeRateProviderController extends Controller
{
    use ExchangeRateProvidersTrait;

    public function __construct(
        private readonly ExchangeRateProviderService $exchangeRateProviderService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $exchangeRateProviders = ExchangeRateProvider::whereCompany()->paginate($limit);

        return ExchangeRateProviderResource::collection($exchangeRateProviders);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(ExchangeRateProviderRequest $request)
    {
        $this->authorize('create', ExchangeRateProvider::class);

        $query = $this->exchangeRateProviderService->checkActiveCurrencies($request);

        if (count($query) !== 0) {
            return respondJson('currency_used', 'Currency used.');
        }

        $checkConverterApi = $this->exchangeRateProviderService->checkProviderStatus($request);

        if ($checkConverterApi->status() == 200) {
            $exchangeRateProvider = $this->exchangeRateProviderService->create($request);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        }

        return $checkConverterApi;
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('view', $exchangeRateProvider);

        return new ExchangeRateProviderResource($exchangeRateProvider);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(ExchangeRateProviderRequest $request, ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('update', $exchangeRateProvider);

        $query = $this->exchangeRateProviderService->checkUpdateActiveCurrencies($exchangeRateProvider, $request);

        if (count($query) !== 0) {
            return respondJson('currency_used', 'Currency used.');
        }

        $checkConverterApi = $this->exchangeRateProviderService->checkProviderStatus($request);

        if ($checkConverterApi->status() == 200) {
            $this->exchangeRateProviderService->update($exchangeRateProvider, $request);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        }

        return $checkConverterApi;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('delete', $exchangeRateProvider);

        if ($exchangeRateProvider->active == true) {
            return respondJson('provider_active', 'Provider Active.');
        }

        $exchangeRateProvider->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function activeProvider(Request $request, Currency $currency)
    {
        $query = ExchangeRateProvider::whereCompany()->whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->get();

        if (count($query) !== 0) {
            return response()->json([
                'success' => true,
                'message' => 'provider_active',
            ], 200);
        }

        return response()->json([
            'error' => 'no_active_provider',
        ], 200);
    }

    public function getRate(Request $request, Currency $currency)
    {
        $settings = CompanySetting::getSettings(['currency'], $request->header('company'));
        $baseCurrency = Currency::findOrFail($settings['currency']);

        $query = ExchangeRateProvider::whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->get()
            ->toArray();

        $exchange_rate = ExchangeRateLog::where('base_currency_id', $currency->id)
            ->where('currency_id', $baseCurrency->id)
            ->orderBy('created_at', 'desc')
            ->value('exchange_rate');

        if ($query) {
            $filter = Arr::only($query[0], ['key', 'driver', 'driver_config']);
            $exchange_rate_value = $this->getExchangeRate($filter, $currency->code, $baseCurrency->code);

            if ($exchange_rate_value->status() == 200) {
                return $exchange_rate_value;
            }
        }
        if ($exchange_rate) {
            return response()->json([
                'exchangeRate' => [$exchange_rate],
            ], 200);
        }

        return response()->json([
            'error' => 'no_exchange_rate_available',
        ], 200);
    }

    public function supportedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        return $this->getSupportedCurrencies($request);
    }

    public function usedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $providerId = $request->provider_id;

        $activeExchangeRateProviders = ExchangeRateProvider::where('active', true)
            ->whereCompany()
            ->when($providerId, function ($query) use ($providerId) {
                return $query->where('id', '<>', $providerId);
            })
            ->pluck('currencies');
        $activeExchangeRateProvider = [];

        foreach ($activeExchangeRateProviders as $data) {
            if (is_array($data)) {
                for ($limit = 0; $limit < count($data); $limit++) {
                    $activeExchangeRateProvider[] = $data[$limit];
                }
            }
        }

        $allExchangeRateProviders = ExchangeRateProvider::whereCompany()->pluck('currencies');
        $allExchangeRateProvider = [];

        foreach ($allExchangeRateProviders as $data) {
            if (is_array($data)) {
                for ($limit = 0; $limit < count($data); $limit++) {
                    $allExchangeRateProvider[] = $data[$limit];
                }
            }
        }

        return response()->json([
            'allUsedCurrencies' => $allExchangeRateProvider ? $allExchangeRateProvider : [],
            'activeUsedCurrencies' => $activeExchangeRateProvider ? $activeExchangeRateProvider : [],
        ]);
    }

    public function usedCurrenciesWithoutRate(Request $request)
    {
        $invoices = Invoice::where('exchange_rate', null)->pluck('currency_id')->toArray();
        $taxes = Tax::where('exchange_rate', null)->pluck('currency_id')->toArray();
        $estimates = Estimate::where('exchange_rate', null)->pluck('currency_id')->toArray();
        $payments = Payment::where('exchange_rate', null)->pluck('currency_id')->toArray();

        $currencies = array_merge($invoices, $taxes, $estimates, $payments);

        return response()->json([
            'currencies' => Currency::whereIn('id', $currencies)->get(),
        ]);
    }

    public function bulkUpdate(BulkExchangeRateRequest $request)
    {
        $bulkExchangeRate = CompanySetting::getSetting('bulk_exchange_rate_configured', $request->header('company'));

        if ($bulkExchangeRate == 'NO') {
            if ($request->currencies) {
                foreach ($request->currencies as $currency) {
                    $currency['exchange_rate'] = $currency['exchange_rate'] ?? 1;

                    $invoices = Invoice::where('currency_id', $currency['id'])->get();

                    if ($invoices) {
                        foreach ($invoices as $invoice) {
                            $invoice->update([
                                'exchange_rate' => $currency['exchange_rate'],
                                'base_discount_val' => $invoice->sub_total * $currency['exchange_rate'],
                                'base_sub_total' => $invoice->sub_total * $currency['exchange_rate'],
                                'base_total' => $invoice->total * $currency['exchange_rate'],
                                'base_tax' => $invoice->tax * $currency['exchange_rate'],
                                'base_due_amount' => $invoice->due_amount * $currency['exchange_rate'],
                            ]);

                            $this->updateItemsExchangeRate($invoice);
                        }
                    }

                    $estimates = Estimate::where('currency_id', $currency['id'])->get();

                    if ($estimates) {
                        foreach ($estimates as $estimate) {
                            $estimate->update([
                                'exchange_rate' => $currency['exchange_rate'],
                                'base_discount_val' => $estimate->sub_total * $currency['exchange_rate'],
                                'base_sub_total' => $estimate->sub_total * $currency['exchange_rate'],
                                'base_total' => $estimate->total * $currency['exchange_rate'],
                                'base_tax' => $estimate->tax * $currency['exchange_rate'],
                            ]);

                            $this->updateItemsExchangeRate($estimate);
                        }
                    }

                    $taxes = Tax::where('currency_id', $currency['id'])->get();

                    if ($taxes) {
                        foreach ($taxes as $tax) {
                            $tax->base_amount = $tax->base_amount * $currency['exchange_rate'];
                            $tax->save();
                        }
                    }

                    $payments = Payment::where('currency_id', $currency['id'])->get();

                    if ($payments) {
                        foreach ($payments as $payment) {
                            $payment->exchange_rate = $currency['exchange_rate'];
                            $payment->base_amount = $payment->amount * $currency['exchange_rate'];
                            $payment->save();
                        }
                    }
                }
            }

            $settings = [
                'bulk_exchange_rate_configured' => 'YES',
            ];

            CompanySetting::setSettings($settings, $request->header('company'));

            return response()->json([
                'success' => true,
            ]);
        }

        return response()->json([
            'error' => false,
        ]);
    }

    private function updateItemsExchangeRate($model): void
    {
        foreach ($model->items as $item) {
            $item->update([
                'exchange_rate' => $model->exchange_rate,
                'base_discount_val' => $item->discount_val * $model->exchange_rate,
                'base_price' => $item->price * $model->exchange_rate,
                'base_tax' => $item->tax * $model->exchange_rate,
                'base_total' => $item->total * $model->exchange_rate,
            ]);

            $this->updateTaxesExchangeRate($item);
        }

        $this->updateTaxesExchangeRate($model);
    }

    private function updateTaxesExchangeRate($model): void
    {
        if ($model->taxes()->exists()) {
            $model->taxes->map(function ($tax) use ($model) {
                $tax->update([
                    'exchange_rate' => $model->exchange_rate,
                    'base_amount' => $tax->amount * $model->exchange_rate,
                ]);
            });
        }
    }
}
