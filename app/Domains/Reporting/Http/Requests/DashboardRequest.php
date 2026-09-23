<?php

namespace App\Domains\Reporting\Http\Requests;

use App\Support\ValidatesReportingPeriod;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    use ValidatesReportingPeriod;

    /**
     * The controller checks the dashboard ability against the company; this
     * class only shapes the period the money chart covers.
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
