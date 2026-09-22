<?php

namespace App\Domains\Contacts\Http\Requests;

use App\Support\ValidatesReportingPeriod;
use Illuminate\Foundation\Http\FormRequest;

class CustomerStatsRequest extends FormRequest
{
    use ValidatesReportingPeriod;

    /**
     * The controller authorizes against the customer; this class only shapes
     * the period the chart covers.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reportingPeriodRules();
    }
}
