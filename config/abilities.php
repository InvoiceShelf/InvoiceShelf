<?php

use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\Note;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Taxation\Models\TaxType;

return [

    /*
    |--------------------------------------------------------------------------
    | Abilities Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for defining the abilities used in the application. Each
    | ability includes a name, a unique identifier (ability), the associated
    | model, and any dependencies on other abilities. This configuration helps
    | manage user permissions and access control throughout the application.
    |
    | `presets` names the role presets (by key) that get the ability by
    | default; the Owner preset gets every ability without being named. After
    | every `migrate` run each preset is offered, once, the abilities it has
    | not been offered before (RolePresetService::applyDefaults), so a new
    | ability needs no migration of its own, and one the super administrator
    | later takes away stays away.
    |
    */

    'abilities' => [

        // Customer
        [
            'name' => 'view customer',
            'ability' => 'view-customer',
            'presets' => ['manager', 'read-only'],
            'model' => Customer::class,
        ],
        [
            'name' => 'create customer',
            'ability' => 'create-customer',
            'presets' => ['manager'],
            'model' => Customer::class,
            'depends_on' => [
                'view-customer',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'edit customer',
            'ability' => 'edit-customer',
            'presets' => ['manager'],
            'model' => Customer::class,
            'depends_on' => [
                'view-customer',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'delete customer',
            'ability' => 'delete-customer',
            'presets' => ['manager'],
            'model' => Customer::class,
            'depends_on' => [
                'view-customer',
            ],
        ],

        // Item
        [
            'name' => 'view item',
            'ability' => 'view-item',
            'presets' => ['manager', 'read-only'],
            'model' => Item::class,
        ],
        [
            'name' => 'create item',
            'ability' => 'create-item',
            'presets' => ['manager'],
            'model' => Item::class,
            'depends_on' => [
                'view-item',
                'view-tax-type',
            ],
        ],
        [
            'name' => 'edit item',
            'ability' => 'edit-item',
            'presets' => ['manager'],
            'model' => Item::class,
            'depends_on' => [
                'view-item',
            ],
        ],
        [
            'name' => 'delete item',
            'ability' => 'delete-item',
            'presets' => ['manager'],
            'model' => Item::class,
            'depends_on' => [
                'view-item',
            ],
        ],

        // Tax Type
        [
            'name' => 'view tax type',
            'ability' => 'view-tax-type',
            'presets' => ['manager', 'read-only'],
            'model' => TaxType::class,
        ],
        [
            'name' => 'create tax type',
            'ability' => 'create-tax-type',
            'presets' => ['manager'],
            'model' => TaxType::class,
            'depends_on' => [
                'view-tax-type',
            ],
        ],
        [
            'name' => 'edit tax type',
            'ability' => 'edit-tax-type',
            'presets' => ['manager'],
            'model' => TaxType::class,
            'depends_on' => [
                'view-tax-type',
            ],
        ],
        [
            'name' => 'delete tax type',
            'ability' => 'delete-tax-type',
            'presets' => ['manager'],
            'model' => TaxType::class,
            'depends_on' => [
                'view-tax-type',
            ],
        ],

        // Estimate
        [
            'name' => 'view estimate',
            'ability' => 'view-estimate',
            'presets' => ['manager', 'read-only'],
            'model' => Estimate::class,
        ],
        [
            'name' => 'create estimate',
            'ability' => 'create-estimate',
            'presets' => ['manager'],
            'model' => Estimate::class,
            'depends_on' => [
                'view-estimate',
                'view-item',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit estimate',
            'ability' => 'edit-estimate',
            'presets' => ['manager'],
            'model' => Estimate::class,
            'depends_on' => [
                'view-item',
                'view-estimate',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete estimate',
            'ability' => 'delete-estimate',
            'presets' => ['manager'],
            'model' => Estimate::class,
            'depends_on' => [
                'view-estimate',
            ],
        ],
        [
            'name' => 'send estimate',
            'ability' => 'send-estimate',
            'presets' => ['manager'],
            'model' => Estimate::class,
        ],

        // Invoice
        [
            'name' => 'view invoice',
            'ability' => 'view-invoice',
            'presets' => ['manager', 'read-only'],
            'model' => Invoice::class,
        ],
        [
            'name' => 'create invoice',
            'ability' => 'create-invoice',
            'presets' => ['manager'],
            'model' => Invoice::class,
            'owner_only' => false,
            'depends_on' => [
                'view-item',
                'view-invoice',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit invoice',
            'ability' => 'edit-invoice',
            'presets' => ['manager'],
            'model' => Invoice::class,
            'depends_on' => [
                'view-item',
                'view-invoice',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete invoice',
            'ability' => 'delete-invoice',
            'presets' => ['manager'],
            'model' => Invoice::class,
            'depends_on' => [
                'view-invoice',
            ],
        ],
        [
            'name' => 'send invoice',
            'ability' => 'send-invoice',
            'presets' => ['manager'],
            'model' => Invoice::class,
        ],

        // Recurring Invoice
        [
            'name' => 'view recurring invoice',
            'ability' => 'view-recurring-invoice',
            'presets' => ['manager', 'read-only'],
            'model' => RecurringInvoice::class,
        ],
        [
            'name' => 'create recurring invoice',
            'ability' => 'create-recurring-invoice',
            'presets' => ['manager'],
            'model' => RecurringInvoice::class,
            'depends_on' => [
                'view-item',
                'view-recurring-invoice',
                'view-tax-type',
                'view-customer',
                'view-all-notes',
                'send-invoice',
            ],
        ],
        [
            'name' => 'edit recurring invoice',
            'ability' => 'edit-recurring-invoice',
            'presets' => ['manager'],
            'model' => RecurringInvoice::class,
            'depends_on' => [
                'view-item',
                'view-recurring-invoice',
                'view-tax-type',
                'view-customer',
                'view-all-notes',
                'send-invoice',
            ],
        ],
        [
            'name' => 'delete recurring invoice',
            'ability' => 'delete-recurring-invoice',
            'presets' => ['manager'],
            'model' => RecurringInvoice::class,
            'depends_on' => [
                'view-recurring-invoice',
            ],
        ],

        // Payment
        [
            'name' => 'view payment',
            'ability' => 'view-payment',
            'presets' => ['manager', 'read-only'],
            'model' => Payment::class,
        ],
        [
            'name' => 'create payment',
            'ability' => 'create-payment',
            'presets' => ['manager'],
            'model' => Payment::class,
            'depends_on' => [
                'view-customer',
                'view-payment',
                'view-invoice',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit payment',
            'ability' => 'edit-payment',
            'presets' => ['manager'],
            'model' => Payment::class,
            'depends_on' => [
                'view-customer',
                'view-payment',
                'view-invoice',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete payment',
            'ability' => 'delete-payment',
            'presets' => ['manager'],
            'model' => Payment::class,
            'depends_on' => [
                'view-payment',
            ],
        ],
        [
            'name' => 'send payment',
            'ability' => 'send-payment',
            'presets' => ['manager'],
            'model' => Payment::class,
        ],

        // Expense
        [
            'name' => 'view expense',
            'ability' => 'view-expense',
            'presets' => ['manager', 'read-only'],
            'model' => Expense::class,
        ],
        [
            'name' => 'create expense',
            'ability' => 'create-expense',
            'presets' => ['manager'],
            'model' => Expense::class,
            'depends_on' => [
                'view-customer',
                'view-expense',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'edit expense',
            'ability' => 'edit-expense',
            'presets' => ['manager'],
            'model' => Expense::class,
            'depends_on' => [
                'view-customer',
                'view-expense',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'delete expense',
            'ability' => 'delete-expense',
            'presets' => ['manager'],
            'model' => Expense::class,
            'depends_on' => [
                'view-expense',
            ],
        ],

        // Custom Field
        [
            'name' => 'view custom field',
            'ability' => 'view-custom-field',
            'presets' => ['manager', 'read-only'],
            'model' => CustomField::class,
        ],
        [
            'name' => 'create custom field',
            'ability' => 'create-custom-field',
            'model' => CustomField::class,
            'depends_on' => [
                'view-custom-field',
            ],
        ],
        [
            'name' => 'edit custom field',
            'ability' => 'edit-custom-field',
            'model' => CustomField::class,
            'depends_on' => [
                'view-custom-field',
            ],
        ],
        [
            'name' => 'delete custom field',
            'ability' => 'delete-custom-field',
            'model' => CustomField::class,
            'depends_on' => [
                'view-custom-field',
            ],
        ],

        // Financial Reports
        [
            'name' => 'view financial reports',
            'ability' => 'view-financial-reports',
            'presets' => ['manager', 'read-only'],
            'model' => null,
        ],

        // Exchange Rate Provider
        [
            'name' => 'view exchange rate provider',
            'ability' => 'view-exchange-rate-provider',
            'presets' => ['manager', 'read-only'],
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
        ],
        [
            'name' => 'create exchange rate provider',
            'ability' => 'create-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
            'depends_on' => [
                'view-exchange-rate-provider',
            ],
        ],
        [
            'name' => 'edit exchange rate provider',
            'ability' => 'edit-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
            'depends_on' => [
                'view-exchange-rate-provider',
            ],
        ],
        [
            'name' => 'delete exchange rate provider',
            'ability' => 'delete-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
            'depends_on' => [
                'view-exchange-rate-provider',
            ],
        ],

        // Settings
        [
            'name' => 'view company dashboard',
            'ability' => 'dashboard',
            'presets' => ['manager', 'read-only'],
            'model' => null,
        ],
        [
            'name' => 'view all notes',
            'ability' => 'view-all-notes',
            'presets' => ['manager', 'read-only'],
            'model' => Note::class,
        ],
        [
            'name' => 'manage notes',
            'ability' => 'manage-all-notes',
            'presets' => ['manager'],
            'model' => Note::class,
            'depends_on' => [
                'view-all-notes',
            ],
        ],
    ],
];
