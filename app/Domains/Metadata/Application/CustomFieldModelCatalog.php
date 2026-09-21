<?php

namespace App\Domains\Metadata\Application;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use InvoiceShelf\Modules\Registry;

/**
 * The models a custom field can be attached to.
 *
 * `custom_fields.model_type` holds one of these keys. It used to be a
 * hardcoded array in the settings modal with nothing on the server side
 * agreeing with it, which is how `Item` came to be queried by the PDF
 * renderer while being impossible to create.
 *
 * Modules add their own from their service provider, with no change here:
 *
 *     Registry::registerDriver('custom_field_model', 'Project', [
 *         'label' => 'Project',
 *         'class' => Project::class,
 *     ]);
 */
class CustomFieldModelCatalog
{
    /** The driver type modules register their own entries under. */
    public const REGISTRY_TYPE = 'custom_field_model';

    /**
     * The models the application itself offers, in the order the editor
     * lists them. Labels are i18n keys the frontend resolves; a module may
     * instead give a plain string, which is shown as it stands.
     *
     * @var array<string, array{label: string, class: class-string}>
     */
    private const BUILT_IN = [
        'Customer' => ['label' => 'settings.custom_fields.model_type.customer', 'class' => Customer::class],
        'Invoice' => ['label' => 'settings.custom_fields.model_type.invoice', 'class' => Invoice::class],
        'Estimate' => ['label' => 'settings.custom_fields.model_type.estimate', 'class' => Estimate::class],
        'Payment' => ['label' => 'settings.custom_fields.model_type.payment', 'class' => Payment::class],
        'Expense' => ['label' => 'settings.custom_fields.model_type.expense', 'class' => Expense::class],
    ];

    /**
     * Every model type on offer, built-in ones first.
     *
     * A module registering a key that already exists replaces it, the same
     * way the marketplace key map lets a rotation key replace a pinned one.
     *
     * @return array<string, array{label: string, class?: class-string}>
     */
    public function all(): array
    {
        $contributed = [];

        foreach (Registry::allDrivers(self::REGISTRY_TYPE) as $name => $meta) {
            $contributed[$name] = [
                'label' => $meta['label'] ?? $name,
                'class' => $meta['class'] ?? null,
            ];
        }

        return array_merge(self::BUILT_IN, $contributed);
    }

    /**
     * The keys a `model_type` may hold, for the validation rule.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * The catalogue as the settings editor consumes it: a list of options
     * rather than a map, shaped like the exchange-rate driver list.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $value => $meta) {
            $options[] = ['value' => $value, 'label' => $meta['label']];
        }

        return $options;
    }
}
