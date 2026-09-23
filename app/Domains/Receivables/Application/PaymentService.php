<?php

namespace App\Domains\Receivables\Application;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Receivables\Contracts\PaymentEmailSender;
use App\Domains\Receivables\Contracts\PaymentExchangeRateRecorder;
use App\Domains\Receivables\Contracts\PaymentNumberAssigner;
use App\Domains\Receivables\Contracts\PaymentPdfDataProvider;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\PdfMetadata;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use App\Support\PublicToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService implements PaymentPdfDataProvider
{
    public function __construct(
        private readonly PaymentAllocationService $paymentAllocationService,
        private readonly MailConfigurator $mailConfigurator,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly PaymentNumberAssigner $paymentNumberAssigner,
        private readonly PaymentExchangeRateRecorder $paymentExchangeRateRecorder,
        private readonly PaymentEmailSender $paymentEmailSender,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{invoice_id: int, amount: int}>  $allocations
     */
    public function create(
        array $attributes,
        array $allocations = [],
        ?iterable $customFields = null,
    ): Payment {
        $payment = DB::transaction(function () use ($attributes, $allocations, $customFields): Payment {
            $payment = Payment::create($attributes);
            $payment->unique_hash = PublicToken::make();

            $numbering = $this->paymentNumberAssigner->next(
                $payment,
                (int) $payment->company_id,
                (int) $payment->customer_id,
            );

            $payment->sequence_number = $numbering->sequence;
            $payment->customer_sequence_number = $numbering->customerSequence;
            $payment->save();

            $this->paymentAllocationService->replace($payment, $allocations);

            $companyCurrency = CompanySetting::getSetting('currency', $payment->company_id);

            if ((string) $payment->currency_id !== $companyCurrency) {
                $this->paymentExchangeRateRecorder->record($payment);
            }

            if ($customFields) {
                $this->customFieldValueWriter->attach($payment, $customFields);
            }

            return $payment;
        });

        return $this->loadPayment($payment);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{invoice_id: int, amount: int}>  $allocations
     */
    public function update(
        Payment $payment,
        array $attributes,
        bool $replaceAllocations,
        array $allocations = [],
        ?iterable $customFields = null,
    ): Payment {
        $payment = DB::transaction(function () use ($payment, $attributes, $replaceAllocations, $allocations, $customFields): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $targetAllocations = $replaceAllocations
                ? $allocations
                : $lockedPayment->allocations()
                    ->get(['invoice_id', 'amount'])
                    ->map(fn ($allocation) => [
                        'invoice_id' => (int) $allocation->invoice_id,
                        'amount' => (int) $allocation->amount,
                    ])
                    ->all();
            $customerChanged = (int) $lockedPayment->customer_id !== (int) $attributes['customer_id'];

            if ($customerChanged && $targetAllocations !== []) {
                throw ValidationException::withMessages([
                    'customer_id' => ['payment_customer_change_requires_unallocated_credit'],
                ]);
            }

            $numbering = $this->paymentNumberAssigner->next(
                $lockedPayment,
                (int) $lockedPayment->company_id,
                (int) $attributes['customer_id'],
            );

            $attributes['customer_sequence_number'] = $numbering->customerSequence;
            $lockedPayment->update($attributes);
            $this->paymentAllocationService->replace($lockedPayment, $targetAllocations);

            $companyCurrency = CompanySetting::getSetting('currency', $lockedPayment->company_id);

            if ((string) $lockedPayment->currency_id !== $companyCurrency) {
                $this->paymentExchangeRateRecorder->record($lockedPayment);
            }

            if ($customFields) {
                $this->customFieldValueWriter->update($lockedPayment, $customFields);
            }

            return $lockedPayment;
        });

        return $this->loadPayment($payment);
    }

    public function delete(Collection $ids): bool
    {
        DB::transaction(function () use ($ids): void {
            foreach ($ids->sort() as $id) {
                $payment = Payment::query()->whereKey($id)->lockForUpdate()->first();

                if (! $payment) {
                    continue;
                }

                $this->paymentAllocationService->replace($payment, []);
                $payment->delete();
            }
        });

        return true;
    }

    public function sendPaymentData(Payment $payment, array $data): array
    {
        $data['payment'] = $payment->toArray();
        $data['user'] = $payment->customer->toArray();
        $data['company'] = Company::find($payment->company_id);
        $data['body'] = $payment->getEmailBody($data['body']);
        $data['attach']['data'] = ($payment->getEmailAttachmentSetting()) ? $this->getPdfData($payment) : null;

        return $data;
    }

    public function send(Payment $payment, array $data): array
    {
        $data = $this->sendPaymentData($payment, $data);

        $this->mailConfigurator->applyCompanyConfig($payment->company_id);

        $this->paymentEmailSender->send($data);

        return [
            'success' => true,
        ];
    }

    public function getPdfData(Payment $payment): mixed
    {
        $payment->loadMissing('allocations.invoice.currency');

        $company = Company::find($payment->company_id);

        // Render in the company's configured language, not the requester's.
        \App::setLocale(CompanySetting::getSetting('language', $company->id));

        $logo = $company->logo_path;
        $shared = [
            'payment' => $payment,
            'company_address' => $payment->getCompanyAddress(),
            'billing_address' => $payment->getCustomerBillingAddress(),
            'notes' => $payment->getNotes(),
            'logo' => $logo ?? null,
        ];
        view()->share($shared);

        $templatePath = PdfTemplateUtils::resolveView('payment', 'payment');

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return Pdf::loadView($templatePath, PdfMetadata::forDocument(
            __('pdf_payment_label'),
            $payment->payment_number,
            $company,
        ));
    }

    private function loadPayment(Payment $payment): Payment
    {
        return Payment::with([
            'customer',
            'allocations.invoice',
            'paymentMethod',
            'fields',
        ])->findOrFail($payment->id);
    }
}
