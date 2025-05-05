<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $address_street_1
 * @property string|null $address_street_2
 * @property string|null $city
 * @property string|null $state
 * @property int|null $country_id
 * @property string|null $zip
 * @property string|null $phone
 * @property string|null $fax
 * @property string|null $type
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $company_id
 * @property int|null $customer_id
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Country|null $country
 * @property-read \App\Models\Customer|null $customer
 * @property-read mixed $country_name
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\AddressFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereAddressStreet1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereAddressStreet2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereFax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereZip($value)
 */
	class Address extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $logo
 * @property string|null $unique_hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $slug
 * @property int|null $owner_id
 * @property string|null $vat_id
 * @property string|null $tax_id
 * @property-read \App\Models\Address|null $address
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $customFieldValues
 * @property-read int|null $custom_field_values_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomField> $customFields
 * @property-read int|null $custom_fields_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Customer> $customers
 * @property-read int|null $customers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Estimate> $estimates
 * @property-read int|null $estimates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExchangeRateLog> $exchangeRateLogs
 * @property-read int|null $exchange_rate_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExchangeRateProvider> $exchangeRateProviders
 * @property-read int|null $exchange_rate_providers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseCategory> $expenseCategories
 * @property-read int|null $expense_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read mixed $logo_path
 * @property-read mixed $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 * @property-read int|null $items_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentMethod> $paymentMethods
 * @property-read int|null $payment_methods_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurringInvoices
 * @property-read int|null $recurring_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanySetting> $settings
 * @property-read int|null $settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaxType> $taxTypes
 * @property-read int|null $tax_types_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Unit> $units
 * @property-read int|null $units_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\CompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereTaxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUniqueHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereVatId($value)
 */
	class Company extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $option
 * @property string $value
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Database\Factories\CompanySettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereCompany($company_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereOption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySetting whereValue($value)
 */
	class CompanySetting extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $phonecode
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Address> $address
 * @property-read int|null $address_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country wherePhonecode($value)
 */
	class Country extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $symbol
 * @property int $precision
 * @property string $thousand_separator
 * @property string $decimal_separator
 * @property int $swap_currency_symbol
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereDecimalSeparator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency wherePrecision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereSwapCurrencySymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereThousandSeparator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereUpdatedAt($value)
 */
	class Currency extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $label
 * @property string $model_type
 * @property string $type
 * @property string|null $placeholder
 * @property array<array-key, mixed>|null $options
 * @property int|null $boolean_answer
 * @property string|null $date_answer
 * @property string|null $time_answer
 * @property string|null $string_answer
 * @property int|null $number_answer
 * @property string|null $date_time_answer
 * @property int $is_required
 * @property int $order
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $customFieldValues
 * @property-read int|null $custom_field_values_count
 * @property-read mixed $default_answer
 * @property-read mixed $in_use
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField applyFilters(array $filters)
 * @method static \Database\Factories\CustomFieldFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereBooleanAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereDateAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereDateTimeAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereNumberAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField wherePlaceholder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereStringAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereTimeAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereUpdatedAt($value)
 */
	class CustomField extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $custom_field_valuable_type
 * @property int $custom_field_valuable_id
 * @property string $type
 * @property int|null $boolean_answer
 * @property string|null $date_answer
 * @property string|null $time_answer
 * @property string|null $string_answer
 * @property int|null $number_answer
 * @property string|null $date_time_answer
 * @property int $custom_field_id
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\CustomField $customField
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $customFieldValuable
 * @property-read mixed $default_answer
 * @method static \Database\Factories\CustomFieldValueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereBooleanAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCustomFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCustomFieldValuableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCustomFieldValuableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereDateAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereDateTimeAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereNumberAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereStringAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereTimeAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereUpdatedAt($value)
 */
	class CustomFieldValue extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $facebook_id
 * @property string|null $google_id
 * @property string|null $github_id
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $website
 * @property bool $enable_portal
 * @property int|null $currency_id
 * @property int|null $company_id
 * @property int|null $creator_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $prefix
 * @property string|null $tax_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Silber\Bouncer\Database\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Address> $addresses
 * @property-read int|null $addresses_count
 * @property-read \App\Models\Address|null $billingAddress
 * @property-read \App\Models\Company|null $company
 * @property-read Customer|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Estimate> $estimates
 * @property-read int|null $estimates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $avatar
 * @property-read mixed $formatted_created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurringInvoices
 * @property-read int|null $recurring_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Silber\Bouncer\Database\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\Address|null $shippingAddress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer applyFilters(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer applyInvoiceFilters(array $filters)
 * @method static \Database\Factories\CustomerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer invoicesBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCustomer($customer_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDisplayName($displayName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEnablePortal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereFacebookId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereGithubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereGoogleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIs($role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIsAll($role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIsNot($role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereTaxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereWebsite($value)
 */
	class Customer extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $from
 * @property string $to
 * @property string $subject
 * @property string $body
 * @property string $mailable_type
 * @property string $mailable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $token
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $mailable
 * @method static \Database\Factories\EmailLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereMailableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereMailableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereUpdatedAt($value)
 */
	class EmailLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $estimate_date
 * @property string|null $expiry_date
 * @property string $estimate_number
 * @property string $status
 * @property string|null $reference_number
 * @property string $tax_per_item
 * @property string $discount_per_item
 * @property string|null $notes
 * @property float|null $discount
 * @property string|null $discount_type
 * @property int|null $discount_val
 * @property int $sub_total
 * @property int $total
 * @property int $tax
 * @property string|null $unique_hash
 * @property int|null $user_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $creator_id
 * @property string|null $template_name
 * @property int|null $customer_id
 * @property float|null $exchange_rate
 * @property int|null $base_discount_val
 * @property int|null $base_sub_total
 * @property int|null $base_total
 * @property int|null $base_tax
 * @property int|null $currency_id
 * @property int|null $sequence_number
 * @property int|null $customer_sequence_number
 * @property string|null $sales_tax_type
 * @property string|null $sales_tax_address_type
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailLog> $emailLogs
 * @property-read int|null $email_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $estimate_pdf_url
 * @property-read mixed $formatted_estimate_date
 * @property-read mixed $formatted_expiry_date
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EstimateItem> $items
 * @property-read int|null $items_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate applyFilters(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate estimatesBetween($start, $end)
 * @method static \Database\Factories\EstimateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereBaseDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereBaseSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereBaseTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereBaseTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCustomer($customer_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereCustomerSequenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereDiscountPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereEstimate($estimate_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereEstimateDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereEstimateNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereSalesTaxAddressType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereSalesTaxType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereSequenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereTaxPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereTemplateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereUniqueHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Estimate whereUserId($value)
 */
	class Estimate extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $discount_type
 * @property float $quantity
 * @property float|null $discount
 * @property int|null $discount_val
 * @property int $price
 * @property int $tax
 * @property int $total
 * @property int|null $item_id
 * @property int $estimate_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $unit_name
 * @property string|null $exchange_rate
 * @property int|null $base_discount_val
 * @property int|null $base_price
 * @property int|null $base_tax
 * @property int|null $base_total
 * @property-read \App\Models\Estimate $estimate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read \App\Models\Item|null $item
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @method static \Database\Factories\EstimateItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereBaseDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereBaseTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereBaseTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereCompany($company_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereEstimateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereUnitName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstimateItem whereUpdatedAt($value)
 */
	class EstimateItem extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $base_currency_id
 * @property int|null $currency_id
 * @property float|null $exchange_rate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Currency|null $currency
 * @method static \Database\Factories\ExchangeRateLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereBaseCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateLog whereUpdatedAt($value)
 */
	class ExchangeRateLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $driver
 * @property string $key
 * @property array<array-key, mixed>|null $currencies
 * @property array<array-key, mixed>|null $driver_config
 * @property bool $active
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Database\Factories\ExchangeRateProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereCurrencies($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereDriverConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeRateProvider whereUpdatedAt($value)
 */
	class ExchangeRateProvider extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $expense_date
 * @property string|null $attachment_receipt
 * @property int $amount
 * @property string|null $notes
 * @property int $expense_category_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $user_id
 * @property int|null $creator_id
 * @property int|null $customer_id
 * @property float|null $exchange_rate
 * @property int|null $base_amount
 * @property int|null $currency_id
 * @property int|null $payment_method_id
 * @property-read \App\Models\ExpenseCategory $category
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $formatted_created_at
 * @property-read mixed $formatted_expense_date
 * @property-read mixed $receipt
 * @property-read mixed $receipt_meta
 * @property-read mixed $receipt_url
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\PaymentMethod|null $paymentMethod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense applyFilters(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense expensesAttributes()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense expensesBetween($start, $end)
 * @method static \Database\Factories\ExpenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereAttachmentReceipt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCategory($categoryId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCategoryName($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpense($expense_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpenseCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpenseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereUser($customer_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereUserId($value)
 */
	class Expense extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read mixed $amount
 * @property-read mixed $formatted_created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory applyFilters(array $filters)
 * @method static \Database\Factories\ExpenseCategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereCategory($category_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseCategory whereUpdatedAt($value)
 */
	class ExpenseCategory extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $driver
 * @property bool $set_as_default
 * @property string $credentials
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk applyFilters(array $filters)
 * @method static \Database\Factories\FileDiskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk fileDisksBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereCredentials($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereSetAsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileDisk whereUpdatedAt($value)
 */
	class FileDisk extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $invoice_date
 * @property string|null $due_date
 * @property string $invoice_number
 * @property string|null $reference_number
 * @property string $status
 * @property string $paid_status
 * @property string $tax_per_item
 * @property string $discount_per_item
 * @property string|null $notes
 * @property string|null $discount_type
 * @property float|null $discount
 * @property int|null $discount_val
 * @property int $sub_total
 * @property int $total
 * @property int $tax
 * @property int $due_amount
 * @property int $sent
 * @property int $viewed
 * @property string|null $unique_hash
 * @property int|null $user_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $creator_id
 * @property string|null $template_name
 * @property int|null $customer_id
 * @property int|null $recurring_invoice_id
 * @property float|null $exchange_rate
 * @property int|null $base_discount_val
 * @property int|null $base_sub_total
 * @property int|null $base_total
 * @property int|null $base_tax
 * @property int|null $base_due_amount
 * @property int|null $currency_id
 * @property int|null $sequence_number
 * @property int|null $customer_sequence_number
 * @property string|null $sales_tax_type
 * @property string|null $sales_tax_address_type
 * @property int $overdue
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailLog> $emailLogs
 * @property-read int|null $email_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $allow_edit
 * @property-read mixed $formatted_created_at
 * @property-read mixed $formatted_due_date
 * @property-read mixed $formatted_invoice_date
 * @property-read mixed $formatted_notes
 * @property-read mixed $invoice_pdf_url
 * @property-read mixed $payment_module_enabled
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null $items_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\RecurringInvoice|null $recurringInvoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice applyFilters(array $filters)
 * @method static \Database\Factories\InvoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice invoicesBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBaseDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBaseDueAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBaseSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBaseTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBaseTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCustomer($customer_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCustomerSequenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueStatus($status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoice($invoice_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereOverdue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaidStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereRecurringInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSalesTaxAddressType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSalesTaxType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSequenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTaxPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTemplateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUniqueHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereViewed($value)
 */
	class Invoice extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $discount_type
 * @property int $price
 * @property float $quantity
 * @property float|null $discount
 * @property int $discount_val
 * @property int $tax
 * @property int $total
 * @property int|null $invoice_id
 * @property int|null $item_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $unit_name
 * @property int|null $recurring_invoice_id
 * @property int|null $base_price
 * @property string|null $exchange_rate
 * @property int|null $base_discount_val
 * @property int|null $base_tax
 * @property int|null $base_total
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Item|null $item
 * @property-read \App\Models\RecurringInvoice|null $recurringInvoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem applyInvoiceFilters(array $filters)
 * @method static \Database\Factories\InvoiceItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem invoicesBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem itemAttributes()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereBaseDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereBaseTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereBaseTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCompany($company_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereRecurringInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUnitName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUpdatedAt($value)
 */
	class InvoiceItem extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int|null $company_id
 * @property int|null $unit_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $creator_id
 * @property int|null $currency_id
 * @property int $tax_per_item
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EstimateItem> $estimateItems
 * @property-read int|null $estimate_items_count
 * @property-read mixed $formatted_created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $invoiceItems
 * @property-read int|null $invoice_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @property-read \App\Models\Unit|null $unit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item applyFilters(array $filters)
 * @method static \Database\Factories\ItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereItem($item_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereTaxPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereUnit($unit_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereUpdatedAt($value)
 */
	class Item extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $version
 * @property int $installed
 * @property int $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereInstalled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereVersion($value)
 */
	class Module extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $company_id
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note applyFilters(array $filters)
 * @method static \Database\Factories\NoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereUpdatedAt($value)
 */
	class Note extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $payment_number
 * @property string $payment_date
 * @property string|null $notes
 * @property int $amount
 * @property string|null $unique_hash
 * @property int|null $user_id
 * @property int|null $invoice_id
 * @property int|null $company_id
 * @property int|null $payment_method_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $creator_id
 * @property int|null $customer_id
 * @property float|null $exchange_rate
 * @property int|null $base_amount
 * @property int|null $currency_id
 * @property int|null $sequence_number
 * @property int|null $customer_sequence_number
 * @property int|null $transaction_id
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailLog> $emailLogs
 * @property-read int|null $email_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $formatted_created_at
 * @property-read mixed $formatted_payment_date
 * @property-read mixed $payment_pdf_url
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\PaymentMethod|null $paymentMethod
 * @property-write mixed $settings
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment applyFilters(array $filters)
 * @method static \Database\Factories\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment paymentMethod($paymentMethodId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment paymentNumber($paymentNumber)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment paymentsBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCustomer($customer_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCustomerSequenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePayment($payment_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSequenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUniqueHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUserId($value)
 */
	class Payment extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $driver
 * @property string $type
 * @property array<array-key, mixed>|null $settings
 * @property int $active
 * @property bool $use_test_env
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod applyFilters(array $filters)
 * @method static \Database\Factories\PaymentMethodFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod wherePaymentMethod($payment_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereUseTestEnv($value)
 */
	class PaymentMethod extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $starts_at
 * @property bool $send_automatically
 * @property int|null $customer_id
 * @property int|null $company_id
 * @property string $status
 * @property string|null $next_invoice_at
 * @property int|null $creator_id
 * @property string $frequency
 * @property string $limit_by
 * @property int|null $limit_count
 * @property string|null $limit_date
 * @property int|null $currency_id
 * @property float|null $exchange_rate
 * @property string $tax_per_item
 * @property string $discount_per_item
 * @property string|null $notes
 * @property string|null $discount_type
 * @property string|null $discount
 * @property int|null $discount_val
 * @property int $sub_total
 * @property int $total
 * @property int $tax
 * @property string|null $template_name
 * @property int $due_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $sales_tax_type
 * @property string|null $sales_tax_address_type
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $formatted_created_at
 * @property-read mixed $formatted_limit_date
 * @property-read mixed $formatted_next_invoice_at
 * @property-read mixed $formatted_starts_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice applyFilters(array $filters)
 * @method static \Database\Factories\RecurringInvoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice recurringInvoicesStartBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCustomer($customer_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereDiscountPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereDiscountVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereDueAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereLimitBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereLimitCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereLimitDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereNextInvoiceAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereSalesTaxAddressType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereSalesTaxType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereSendAutomatically($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereTaxPerItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereTemplateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringInvoice whereUpdatedAt($value)
 */
	class RecurringInvoice extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $option
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereOption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $tax_type_id
 * @property int|null $invoice_id
 * @property int|null $estimate_id
 * @property int|null $invoice_item_id
 * @property int|null $estimate_item_id
 * @property int|null $item_id
 * @property int|null $company_id
 * @property string $name
 * @property int $amount
 * @property float $percent
 * @property int $compound_tax
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $exchange_rate
 * @property int|null $base_amount
 * @property int|null $currency_id
 * @property int|null $recurring_invoice_id
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Estimate|null $estimate
 * @property-read \App\Models\EstimateItem|null $estimateItem
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\InvoiceItem|null $invoiceItem
 * @property-read \App\Models\Item|null $item
 * @property-read \App\Models\RecurringInvoice|null $recurringInvoice
 * @property-read \App\Models\TaxType $taxType
 * @method static \Database\Factories\TaxFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax invoicesBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax taxAttributes()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereCompany($company_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereCompoundTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereEstimateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereEstimateItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereInvoiceItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereInvoicesFilters(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereRecurringInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereTaxTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tax whereUpdatedAt($value)
 */
	class Tax extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property float $percent
 * @property bool $compound_tax
 * @property int $collective_tax
 * @property string|null $description
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $type
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tax> $taxes
 * @property-read int|null $taxes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType applyFilters(array $filters)
 * @method static \Database\Factories\TaxTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereCollectiveTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereCompoundTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereTaxType($tax_type_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType whereUpdatedAt($value)
 */
	class TaxType extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string|null $transaction_id
 * @property string|null $unique_hash
 * @property string|null $type
 * @property string $status
 * @property string $transaction_date
 * @property int|null $company_id
 * @property int $invoice_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Invoice $invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUniqueHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 */
	class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit applyFilters(array $filters)
 * @method static \Database\Factories\UnitFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereCompany()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereUnit($unit_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereUpdatedAt($value)
 */
	class Unit extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property string $role
 * @property string|null $remember_token
 * @property string|null $facebook_id
 * @property string|null $google_id
 * @property string|null $github_id
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $website
 * @property int|null $enable_portal
 * @property int|null $currency_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $creator_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Silber\Bouncer\Database\Ability> $abilities
 * @property-read int|null $abilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Address> $addresses
 * @property-read int|null $addresses_count
 * @property-read \App\Models\Address|null $billingAddress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read User|null $creator
 * @property-read \App\Models\Currency|null $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Customer> $customers
 * @property-read int|null $customers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Estimate> $estimates
 * @property-read int|null $estimates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read mixed $avatar
 * @property-read mixed $formatted_created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 * @property-read int|null $items_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurringInvoices
 * @property-read int|null $recurring_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Silber\Bouncer\Database\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserSetting> $settings
 * @property-read int|null $settings_count
 * @property-read \App\Models\Address|null $shippingAddress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User applyFilters(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User applyInvoiceFilters(array $filters)
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User invoicesBetween($start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User paginateData($limit)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDisplayName($displayName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEnablePortal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFacebookId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGithubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIs($role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAll($role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsNot($role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOrder($orderByField, $orderBy)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSearch($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSuperAdmin()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWebsite($value)
 */
	class User extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $key
 * @property string $value
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereValue($value)
 */
	class UserSetting extends \Eloquent {}
}

