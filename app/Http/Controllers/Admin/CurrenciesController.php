<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrenciesController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencyService,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $currencies = $this->currencyService->getAllWithCommonFirst();

        return CurrencyResource::collection($currencies);
    }
}
