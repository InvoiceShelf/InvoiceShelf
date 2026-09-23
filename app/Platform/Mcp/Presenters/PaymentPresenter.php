<?php

namespace App\Platform\Mcp\Presenters;

use App\Domains\Receivables\Models\Payment;

final class PaymentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'number' => $payment->payment_number,
            'date' => Date::of($payment, 'payment_date'),
            'customer' => ['id' => $payment->customer_id, 'name' => $payment->customer?->name],
            'amount' => Money::of($payment->amount, $payment->currency_id),
            'method' => $payment->paymentMethod?->name,
            'app_url' => Link::to("payments/{$payment->id}/view"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Payment $payment): array
    {
        $payment->loadMissing('allocations.invoice');
        $allocated = (int) $payment->allocations->sum('amount');

        return self::summary($payment) + [
            'exchange_rate' => (float) $payment->exchange_rate,
            'notes' => Text::plain($payment->notes),
            'allocations' => $payment->allocations
                ->map(fn ($allocation) => [
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_number' => $allocation->invoice?->invoice_number,
                    'amount' => Money::of($allocation->amount, $payment->currency_id),
                ])
                ->values()
                ->all(),
            'unapplied' => Money::of(max(0, (int) $payment->amount - $allocated), $payment->currency_id),
            'custom_fields' => CustomFieldAnswers::of($payment),
        ];
    }
}
