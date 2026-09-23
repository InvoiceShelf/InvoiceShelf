<?php

declare(strict_types=1);

namespace App\Platform\Persistence;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\ImpersonationLog;
use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Models\UserSetting;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Country;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\CustomFieldValue;
use App\Domains\Metadata\Models\Note;
use App\Domains\Money\Models\Currency;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Receivables\Models\Transaction;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\EstimateItem;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Modules\Models\MarketplaceCredential;
use App\Platform\Modules\Models\MarketplaceOperation;
use App\Platform\Modules\Models\Module;
use App\Platform\Operations\Models\Setting;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Database\Role;

/**
 * Stable database identities for Eloquent models.
 *
 * Model namespaces are an implementation detail and will change as contexts
 * move into app/Domains and app/Platform. These aliases are persisted instead,
 * so future namespace refactors cannot invalidate polymorphic records.
 */
final class ModelIdentityMap
{
    public const ESTIMATE_ALIAS = 'estimate';

    public const INVOICE_ALIAS = 'invoice';

    public const PAYMENT_ALIAS = 'payment';

    /**
     * @return array<string, class-string<Model>>
     */
    public static function aliases(): array
    {
        return [
            'address' => Address::class,
            'company' => Company::class,
            'company_invitation' => CompanyInvitation::class,
            'company_setting' => CompanySetting::class,
            'country' => Country::class,
            'currency' => Currency::class,
            'custom_field' => CustomField::class,
            'custom_field_value' => CustomFieldValue::class,
            'customer' => Customer::class,
            'email_log' => EmailLog::class,
            self::ESTIMATE_ALIAS => Estimate::class,
            'estimate_item' => EstimateItem::class,
            'exchange_rate_log' => ExchangeRateLog::class,
            'exchange_rate_provider' => ExchangeRateProvider::class,
            'expense' => Expense::class,
            'expense_category' => ExpenseCategory::class,
            'file_disk' => FileDisk::class,
            'impersonation_log' => ImpersonationLog::class,
            self::INVOICE_ALIAS => Invoice::class,
            'invoice_item' => InvoiceItem::class,
            'item' => Item::class,
            'marketplace_credential' => MarketplaceCredential::class,
            'marketplace_operation' => MarketplaceOperation::class,
            'mcp_connection' => McpConnection::class,
            'module' => Module::class,
            'note' => Note::class,
            self::PAYMENT_ALIAS => Payment::class,
            'payment_allocation' => PaymentAllocation::class,
            'payment_method' => PaymentMethod::class,
            'recurring_invoice' => RecurringInvoice::class,
            'setting' => Setting::class,
            'tax' => Tax::class,
            'tax_type' => TaxType::class,
            'transaction' => Transaction::class,
            'unit' => Unit::class,
            'user' => User::class,
            'user_setting' => UserSetting::class,
            'bouncer_ability' => Ability::class,
            'bouncer_role' => Role::class,
        ];
    }

    public static function enforce(): void
    {
        Relation::enforceMorphMap(self::aliases());
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function aliasFor(string $model): string
    {
        $alias = array_search($model, self::aliases(), true);

        if (! is_string($alias)) {
            throw new InvalidArgumentException("Model [{$model}] has no stable database identity.");
        }

        return $alias;
    }

    /**
     * Preserve the model discriminator exposed by the existing v1 API.
     */
    public static function publicType(string $databaseType): string
    {
        $model = self::aliases()[$databaseType] ?? null;

        if ($model === null || str_starts_with($databaseType, 'bouncer_')) {
            return $databaseType;
        }

        return 'App\\Models\\'.class_basename($model);
    }
}
