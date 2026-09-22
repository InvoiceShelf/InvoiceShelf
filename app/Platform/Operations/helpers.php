<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
|
| These three run during boot — view composers, the blade shell, the PDF
| layouts — which means they can be reached before the installer has created
| a single table. Each therefore probes the installation first and answers
| null rather than letting a "no such table" error escape.
|
*/

/**
 * Read one company-scoped setting, or null when there is no database yet.
 *
 * @param  string  $key
 * @param  mixed  $company_id
 * @return mixed
 */
function get_company_setting($key, $company_id)
{
    return InstallationState::isDbCreated()
        ? CompanySetting::getSetting($key, $company_id)
        : null;
}

/**
 * Read one instance-wide setting, or null when there is no database yet.
 *
 * @param  string  $key
 * @return mixed
 */
function get_app_setting($key)
{
    return InstallationState::isDbCreated()
        ? Setting::getSetting($key)
        : null;
}

/**
 * Resolve the <title> for the SPA shell.
 *
 * The customer portal gets its own per-company title; everything else gets the
 * instance-wide admin title. An empty or missing setting falls through to the
 * product name, and an uninstalled instance has no title at all.
 *
 * @param  mixed  $company_id
 * @return string|null
 */
function get_page_title($company_id)
{
    if (! InstallationState::isDbCreated()) {
        return null;
    }

    $configured = Route::currentRouteName() === 'customer.dashboard'
        ? CompanySetting::getSetting('customer_portal_page_title', $company_id)
        : Setting::getSetting('admin_page_title');

    return $configured ?: 'InvoiceShelf - Self Hosted Invoicing Platform';
}

/*
|--------------------------------------------------------------------------
| Request path matching
|--------------------------------------------------------------------------
*/

/**
 * Does the current request path match one of the given patterns?
 *
 * @param  string|array  $path  One pattern, or a list of them.
 * @return bool
 */
function is_url($path)
{
    return Request::is(...(array) $path);
}

/**
 * Blade sugar: emit the marker class when the current path matches.
 *
 * @param  string|array  $path
 * @param  string  $active  Returned on a match.
 * @return string
 */
function set_active($path, $active = 'active')
{
    return is_url($path) ? $active : '';
}

/*
|--------------------------------------------------------------------------
| Custom fields
|--------------------------------------------------------------------------
*/

/**
 * Which custom_field_values column stores an answer of this field type.
 *
 * Every value column is typed, so the field's declared type decides where the
 * answer is written and read. Phone numbers are kept alongside numbers, and an
 * unrecognised type is treated as free text so an unknown field never loses
 * its answer.
 *
 * @return string
 */
function getCustomFieldValueKey(string $type)
{
    return match ($type) {
        'Number', 'Phone' => 'number_answer',
        'Switch' => 'boolean_answer',
        'Date' => 'date_answer',
        'Time' => 'time_answer',
        'DateTime' => 'date_time_answer',
        // 'Input', 'TextArea', 'Url', 'Dropdown' — and anything unmapped.
        default => 'string_answer',
    };
}

/**
 * Every existing slug that could collide with $slug for this model type.
 *
 * Prefix-matched in one query so the caller can test the base slug and all of
 * its numbered variants without going back to the database. The field being
 * renamed is excluded, otherwise it would collide with itself.
 *
 * @param  string  $type  Model type the field is attached to.
 * @param  string  $slug  Base slug to match as a prefix.
 * @param  mixed  $companyId  Company whose slugs are being compared.
 * @param  int  $id  Custom field to leave out of the comparison.
 * @return Collection
 */
function getRelatedSlugs($type, $slug, $companyId, $id = 0)
{
    return CustomField::query()
        ->select('slug')
        ->where('company_id', $companyId)
        ->where('model_type', $type)
        ->where('slug', 'like', $slug.'%')
        ->where('id', '!=', $id)
        ->get();
}

/**
 * Build the unique storage slug for a custom field.
 *
 * The shape is CUSTOM_<MODEL>_<LABEL>, upper-cased with underscores. When that
 * is taken within the same company, _1 .. _10 are tried in turn; an eleventh
 * collision is a caller problem, not something to paper over with a random
 * suffix.
 *
 * Uniqueness is per company. It used to be global, so the second tenant to
 * name a field "Supply Date" got CUSTOM_INVOICE_SUPPLY_DATE_1 on account of
 * a company they cannot see, and the eleventh could not create the field at
 * all. Nothing resolves a definition by slug outside the record that holds
 * the answer, so narrowing it costs nothing.
 *
 * @param  string  $model  Model type the field is attached to.
 * @param  string  $title  Human label to slugify.
 * @param  mixed  $companyId  Company the field belongs to.
 * @param  int  $id  Custom field being renamed, if any.
 * @return string
 *
 * @throws Exception When every candidate is already in use.
 */
function clean_slug($model, $title, $companyId, $id = 0)
{
    $base = Str::upper('CUSTOM_'.$model.'_'.Str::slug($title, '_'));

    $taken = getRelatedSlugs($model, $base, $companyId, $id)->pluck('slug')->all();

    $candidates = [$base, ...array_map(fn ($n) => $base.'_'.$n, range(1, 10))];

    foreach ($candidates as $candidate) {
        if (! in_array($candidate, $taken)) {
            return $candidate;
        }
    }

    throw new Exception('Can not create a unique slug');
}

/*
|--------------------------------------------------------------------------
| Output
|--------------------------------------------------------------------------
*/

/**
 * Render a minor-unit amount as currency markup for a PDF template.
 *
 * Two things set this apart from the ordinary money formatter. The symbol is
 * wrapped in a DejaVu Sans span, because the template fonts carry no glyph for
 * most currency symbols. And the sign is prefixed to the finished string
 * instead of being formatted into it, so a negative amount reads "-$24,738.00"
 * with the symbol still against the digits in either symbol position.
 *
 * The sign is decided on the formatted digits rather than on the input: an
 * amount that rounds away at the currency's precision prints as zero, and
 * "-$0.00" is not a number anyone owes.
 *
 * @param  int|float|string|null  $money  Amount in minor units (cents).
 * @param  Currency|null  $currency
 * @return string
 */
function format_money_pdf($money, $currency = null)
{
    $amount = $money / 100;

    // Quirk, deliberately preserved: with no currency in hand this falls back
    // to company 1's setting rather than to the amount's own company.
    $currency = $currency ?: Currency::findOrFail(CompanySetting::getSetting('currency', 1));

    $digits = number_format(
        abs($amount),
        $currency->precision,
        $currency->decimal_separator,
        $currency->thousand_separator
    );

    $symbol = '<span style="font-family: DejaVu Sans;">'.$currency->symbol.'</span>';

    $rendered = $currency->swap_currency_symbol
        ? $digits.$symbol
        : $symbol.$digits;

    $signed = $amount < 0 && preg_match('/[1-9]/', $digits) === 1;

    return $signed ? '-'.$rendered : $rendered;
}

/**
 * The validation-shaped rejection controllers use for domain conflicts.
 *
 * @param  string  $error  Machine-readable key the SPA switches on.
 * @param  string  $message  Human-readable explanation.
 * @return JsonResponse
 */
function respondJson($error, $message)
{
    return response()->json([
        'error' => $error,
        'message' => $message,
    ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
}
