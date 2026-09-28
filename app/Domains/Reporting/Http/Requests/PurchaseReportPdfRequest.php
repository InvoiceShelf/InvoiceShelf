<?php

namespace App\Domains\Reporting\Http\Requests;

use App\Domains\Accounts\Models\Company;
use App\Domains\Purchases\Http\Requests\PurchaseReportRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Silber\Bouncer\BouncerFacade;

class PurchaseReportPdfRequest extends FormRequest
{
    private ?Company $reportCompany = null;

    /**
     * The company named by the hash in the report URL, looked up once.
     */
    public function company(): Company
    {
        return $this->reportCompany ??= Company::query()
            ->where('unique_hash', $this->route('hash'))
            ->firstOrFail();
    }

    public function authorize(): bool
    {
        BouncerFacade::scope()->to($this->company()->id);

        return Gate::allows('view report', $this->company());
    }

    public function rules(): array
    {
        return PurchaseReportRequest::rulesFor($this->company()->id);
    }
}
