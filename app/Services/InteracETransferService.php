<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\InteracETransferLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class InteracETransferService
{
    private string $mailboxPrefix = '';

    /**
     * Sync e-transfer notifications for all enabled companies or a specific company.
     */
    public function sync(?int $companyId = null): array
    {
        if (! function_exists('imap_open')) {
            Log::warning('Interac e-Transfer sync skipped because PHP IMAP extension is missing.');

            return [];
        }

        $companies = $this->eligibleCompanies($companyId);

        $results = [];
        foreach ($companies as $company) {
            $results[$company->id] = $this->syncCompany($company);
        }

        return $results;
    }

    /**
     * Attempt to connect to the mailbox using provided settings.
     */
    public function testConnection(array $settings): array
    {
        if (! function_exists('imap_open')) {
            return [
                'status' => 'missing_extension',
            ];
        }

        if (! $settings['enabled']) {
            return [
                'status' => 'disabled',
            ];
        }

        $connection = $this->connect($settings);

        if (! $connection) {
            return [
                'status' => 'connection_failed',
                'errors' => imap_errors() ?: [],
            ];
        }

        $isAlive = @imap_ping($connection);

        @imap_close($connection);

        return [
            'status' => $isAlive ? 'ok' : 'connection_failed',
        ];
    }

    /**
     * Merge request payload into resolved settings for connection testing.
     */
    public function mergeSettingsFromPayload(Company $company, array $payload): array
    {
        $settings = $this->getSettings($company);

        $map = [
            'enabled' => 'interac_enabled',
            'host' => 'interac_imap_host',
            'port' => 'interac_imap_port',
            'encryption' => 'interac_imap_encryption',
            'username' => 'interac_imap_username',
            'password' => 'interac_imap_password',
            'folder' => 'interac_imap_folder',
            'validate_cert' => 'interac_imap_validate_cert',
            'mark_as_read' => 'interac_imap_mark_as_read',
            'move_to' => 'interac_imap_move_to',
            'sender_filter' => 'interac_sender_filter',
        ];

        foreach ($map as $key => $option) {
            if (! array_key_exists($option, $payload)) {
                continue;
            }

            $settings[$key] = match ($key) {
                'enabled', 'mark_as_read', 'validate_cert' => filter_var($payload[$option], FILTER_VALIDATE_BOOLEAN),
                'port' => (int) $payload[$option],
                default => $payload[$option],
            };
        }

        return $settings;
    }

    /**
     * Fetch companies with Interac automation enabled.
     */
    protected function eligibleCompanies(?int $companyId = null): Collection
    {
        $query = Company::query();

        if ($companyId) {
            $query->where('id', $companyId);
        }

        $query->whereHas('settings', function ($settings) {
            $settings->where('option', 'interac_enabled')
                ->whereIn('value', ['1', 1, 'true', true, 'on']);
        });

        return $query->get();
    }

    /**
     * Sync messages for a single company.
     */
    public function syncCompany(Company $company): array
    {
        $settings = $this->getSettings($company);

        if (! $settings['enabled']) {
            return ['status' => 'disabled'];
        }

        if (! $settings['host'] || ! $settings['username'] || ! $settings['password']) {
            return ['status' => 'missing_settings'];
        }

        $lock = Cache::lock('interac-sync-'.$company->id, 120);

        if (! $lock->get()) {
            return ['status' => 'locked'];
        }

        $checked = 0;
        $created = 0;
        $connection = null;

        try {
            $connection = $this->connect($settings);

            if (! $connection) {
                Log::warning('Interac e-Transfer connection failed', [
                    'company_id' => $company->id,
                    'host' => $settings['host'],
                    'errors' => imap_errors(),
                ]);

                return ['status' => 'connection_failed'];
            }

            $uids = $this->search($connection, $settings['sender_filter']);

            foreach ($uids as $uid) {
                $checked++;
                $result = $this->processMessage($connection, (int) $uid, $company, $settings);

                if ($result) {
                    $created++;
                }
            }

            imap_expunge($connection);
        } finally {
            if ($connection) {
                imap_close($connection);
            }
            optional($lock)->release();
        }

        return [
            'status' => 'ok',
            'messages_checked' => $checked,
            'payments_created' => $created,
        ];
    }

    /**
     * Open the IMAP connection.
     */
    protected function connect(array $settings)
    {
        $flags = match ($settings['encryption']) {
            'ssl' => '/ssl',
            'tls' => '/tls',
            default => '/notls',
        };

        $validateFlag = $settings['validate_cert'] ? '' : '/novalidate-cert';

        $this->mailboxPrefix = sprintf(
            '{%s:%d/imap%s%s}',
            $settings['host'],
            $settings['port'],
            $flags,
            $validateFlag
        );

        return @imap_open(
            $this->mailboxPrefix.$settings['folder'],
            $settings['username'],
            $settings['password']
        );
    }

    /**
     * Find unseen messages matching the sender filter.
     */
    protected function search($connection, ?string $senderFilter): array
    {
        $criteria = 'UNSEEN';

        if ($senderFilter) {
            $criteria .= ' FROM "'.$senderFilter.'"';
        }

        $messages = imap_search($connection, $criteria, SE_UID);

        return $messages ?: [];
    }

    /**
     * Process a single email.
     */
    protected function processMessage($connection, int $uid, Company $company, array $settings): bool
    {
        $overview = imap_fetch_overview($connection, (string) $uid, FT_UID);
        if (! $overview || ! isset($overview[0])) {
            $this->finalizeMessage($connection, $uid, $settings, null);

            return false;
        }

        $meta = $overview[0];

        $messageId = $meta->message_id ?? null;
        $senderHeader = $meta->from ?? '';
        $subject = $meta->subject ?? '';
        $sentAt = $meta->date ?? null;
        $senderParts = imap_rfc822_parse_adrlist($senderHeader, 'noreply@example.com')[0] ?? null;
        $senderEmail = $senderParts?->mailbox && $senderParts?->host ? "{$senderParts->mailbox}@{$senderParts->host}" : null;
        $senderName = $senderParts?->personal ?: $senderEmail;

        $htmlBody = $this->getHtmlBody($connection, $uid);

        $paymentInfo = $this->parsePaymentInfo($htmlBody, $subject, $senderName);

        if (! $paymentInfo) {
            $this->finalizeMessage($connection, $uid, $settings, $senderName);

            return false;
        }

        if ($this->alreadyProcessed($company, $paymentInfo['reference'], $messageId)) {
            $this->finalizeMessage($connection, $uid, $settings, $senderName);

            return false;
        }

        $customer = $this->findOrCreateCustomer(
            $company,
            $paymentInfo['sender'] ?? $senderName ?? 'Interac Customer',
            $senderEmail
        );

        $paymentMethodId = $this->getPaymentMethod($company);

        $payment = $this->createPayment(
            $company,
            $customer,
            $paymentMethodId,
            $paymentInfo
        );

        InteracETransferLog::create([
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'reference' => $paymentInfo['reference'],
            'message_id' => $messageId,
            'sender_name' => $customer->name,
            'sender_email' => $senderEmail,
            'amount' => $paymentInfo['amount_cents'],
            'meta' => [
                'subject' => $subject,
                'sent_at' => $sentAt,
                'uid' => $uid,
            ],
        ]);

        $this->finalizeMessage($connection, $uid, $settings, $customer->name);

        return true;
    }

    /**
     * Mark the message and optionally move to a folder.
     */
    protected function finalizeMessage($connection, int $uid, array $settings, ?string $customerName): void
    {
        if ($settings['mark_as_read']) {
            @imap_setflag_full($connection, (string) $uid, '\\Seen', ST_UID);
        }

        if ($settings['move_to']) {
            $folder = $this->buildFolderName($settings['move_to'], $customerName);

            $this->moveToFolder($connection, $uid, $folder);
        }
    }

    /**
     * Create a payment record.
     */
    protected function createPayment(Company $company, Customer $customer, int $paymentMethodId, array $paymentInfo): Payment
    {
        $serial = (new SerialNumberFormatter())
            ->setModel(new Payment())
            ->setCompany($company->id)
            ->setCustomer($customer->id)
            ->setNextNumbers();

        $payment = Payment::create([
            'payment_number' => $serial->getNextNumber(),
            'payment_date' => $paymentInfo['payment_date'],
            'amount' => $paymentInfo['amount_cents'],
            'payment_method_id' => $paymentMethodId,
            'customer_id' => $customer->id,
            'exchange_rate' => 1,
            'base_amount' => $paymentInfo['amount_cents'],
            'currency_id' => $customer->currency_id,
            'company_id' => $company->id,
            'creator_id' => $company->owner_id,
            'notes' => 'Interac e-Transfer payment (Ref: '.$paymentInfo['reference'].')',
        ]);

        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);
        $payment->sequence_number = $serial->nextSequenceNumber;
        $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $payment->save();

        return $payment;
    }

    /**
     * Ensure Interac payment method exists.
     */
    protected function getPaymentMethod(Company $company): int
    {
        $method = PaymentMethod::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', ['interac e-transfer'])
            ->first();

        if (! $method) {
            $method = PaymentMethod::create([
                'name' => 'Interac e-Transfer',
                'company_id' => $company->id,
            ]);
        }

        return $method->id;
    }

    /**
     * Create or locate the sender as a customer.
     */
    protected function findOrCreateCustomer(Company $company, string $name, ?string $email): Customer
    {
        $customer = Customer::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        $currencyId = CompanySetting::getSetting('currency', $company->id)
            ?: $customer?->currency_id
            ?: Currency::first()?->id;
        $currencyId = (int) $currencyId;

        if (! $customer) {
            $customer = Customer::create([
                'name' => $name,
                'email' => $email,
                'currency_id' => $currencyId,
                'company_id' => $company->id,
                'enable_portal' => false,
                'creator_id' => $company->owner_id,
            ]);
        } elseif (! $customer->currency_id) {
            $customer->currency_id = $currencyId;
            $customer->save();
        }

        return $customer;
    }

    /**
     * Avoid processing the same email twice.
     */
    protected function alreadyProcessed(Company $company, string $reference, ?string $messageId): bool
    {
        return InteracETransferLog::where('company_id', $company->id)
            ->where(function ($query) use ($reference, $messageId) {
                $query->where('reference', $reference);
                if ($messageId) {
                    $query->orWhere('message_id', $messageId);
                }
            })
            ->exists();
    }

    /**
     * Parse payment details from HTML.
     */
    protected function parsePaymentInfo(?string $html, string $subject, ?string $sender): ?array
    {
        if (! $html) {
            return null;
        }

        $plain = preg_replace('/\s+/', ' ', strip_tags($html));

        $amount = null;
        if (preg_match('/\\$\\s*([0-9][0-9,]*(?:\\.\\d{1,2})?)/', $plain, $amountMatch)) {
            $amount = (float) str_replace(',', '', $amountMatch[1]);
        } elseif (preg_match('/([0-9][0-9,]*(?:\\.\\d{1,2})?)\\s*(?:CAD)?/i', $plain, $amountMatch)) {
            $amount = (float) str_replace(',', '', $amountMatch[1]);
        }

        if (! $amount) {
            return null;
        }

        $reference = null;
        if (preg_match('/Reference Number:?\\s*([A-Za-z0-9\\-]+)/i', $plain, $refMatch)) {
            $reference = $refMatch[1];
        } elseif (preg_match('/Ref[:#]?\\s*([A-Za-z0-9\\-]+)/i', $subject, $refMatch)) {
            $reference = $refMatch[1];
        }

        if (! $reference) {
            return null;
        }

        $date = null;
        if (preg_match('/Date:?\\s*([A-Za-z]{3,9}\\s+\\d{1,2},\\s+\\d{4})/i', $plain, $dateMatch)) {
            try {
                $date = Carbon::parse($dateMatch[1])->format('Y-m-d');
            } catch (\Throwable $th) {
                $date = null;
            }
        }

        if (! $sender && preg_match('/Sent From:?\\s*([^$]+?)(?:Date|Reference|$)/i', $plain, $senderMatch)) {
            $sender = trim($senderMatch[1]);
        }

        return [
            'amount_cents' => (int) round($amount * 100),
            'reference' => $reference,
            'payment_date' => $date ?? now()->toDateString(),
            'sender' => $sender,
        ];
    }

    /**
     * Fetch and decode the HTML body.
     */
    protected function getHtmlBody($connection, int $uid): ?string
    {
        $structure = imap_fetchstructure($connection, (string) $uid, FT_UID);

        if (! $structure) {
            return null;
        }

        if ($structure->type === TYPETEXT && strtolower($structure->subtype ?? '') === 'html') {
            return $this->decodeBody(imap_body($connection, (string) $uid, FT_UID | FT_PEEK), $structure->encoding ?? 0);
        }

        if ($structure->type === TYPETEXT && strtolower($structure->subtype ?? '') === 'plain') {
            return $this->decodeBody(imap_body($connection, (string) $uid, FT_UID | FT_PEEK), $structure->encoding ?? 0);
        }

        if (! isset($structure->parts)) {
            return null;
        }

        $plainFallback = null;

        foreach ($structure->parts as $index => $part) {
            $partNumber = (string) ($index + 1);

            if (strtolower($part->subtype ?? '') === 'html') {
                $body = imap_fetchbody($connection, (string) $uid, $partNumber, FT_UID | FT_PEEK);

                return $this->decodeBody($body, $part->encoding ?? 0);
            }

            if (strtolower($part->subtype ?? '') === 'plain') {
                $body = imap_fetchbody($connection, (string) $uid, $partNumber, FT_UID | FT_PEEK);
                $plainFallback = $this->decodeBody($body, $part->encoding ?? 0);
            }

            if (isset($part->parts) && count($part->parts)) {
                foreach ($part->parts as $subIndex => $subPart) {
                    $subPartNumber = $partNumber.'.'.($subIndex + 1);

                    if (strtolower($subPart->subtype ?? '') === 'html') {
                        $body = imap_fetchbody($connection, (string) $uid, $subPartNumber, FT_UID | FT_PEEK);

                        return $this->decodeBody($body, $subPart->encoding ?? 0);
                    }

                    if (strtolower($subPart->subtype ?? '') === 'plain') {
                        $body = imap_fetchbody($connection, (string) $uid, $subPartNumber, FT_UID | FT_PEEK);
                        $plainFallback = $this->decodeBody($body, $subPart->encoding ?? 0);
                    }
                }
            }
        }

        return $plainFallback;
    }

    /**
     * Decode a raw IMAP body based on encoding.
     */
    protected function decodeBody(?string $body, int $encoding): ?string
    {
        if ($body === null) {
            return null;
        }

        return match ($encoding) {
            ENCBASE64 => base64_decode($body),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Build a sanitized folder name from template.
     */
    protected function buildFolderName(string $template, ?string $customerName): string
    {
        $safeName = $customerName ? Str::slug($customerName, '_') : 'interac';

        if (str_contains($template, '{customer}')) {
            return str_replace('{customer}', $safeName, $template);
        }

        return trim($template.'-'.$safeName, '-');
    }

    /**
     * Move message to a mailbox, creating it when needed.
     */
    protected function moveToFolder($connection, int $uid, string $folder): void
    {
        if (! $folder) {
            return;
        }

        $encodedFolder = imap_utf7_encode($this->mailboxPrefix.$folder);

        if (! @imap_createmailbox($connection, $encodedFolder)) {
            // ignore if it already exists
        }

        @imap_mail_move($connection, (string) $uid, $folder, CP_UID);
    }

    /**
     * Resolve company level settings with defaults.
     */
    protected function getSettings(Company $company): array
    {
        $keys = [
            'interac_enabled',
            'interac_imap_host',
            'interac_imap_port',
            'interac_imap_encryption',
            'interac_imap_username',
            'interac_imap_password',
            'interac_imap_folder',
            'interac_imap_validate_cert',
            'interac_imap_mark_as_read',
            'interac_imap_move_to',
            'interac_sender_filter',
        ];

        $raw = CompanySetting::getSettings($keys, $company->id)->toArray();

        $defaults = [
            'interac_enabled' => false,
            'interac_imap_host' => null,
            'interac_imap_port' => 993,
            'interac_imap_encryption' => 'ssl',
            'interac_imap_username' => null,
            'interac_imap_password' => null,
            'interac_imap_folder' => 'INBOX',
            'interac_imap_validate_cert' => true,
            'interac_imap_mark_as_read' => true,
            'interac_imap_move_to' => '',
            'interac_sender_filter' => 'notify@payments.interac.ca',
        ];

        $settings = array_merge($defaults, $raw);

        return [
            'enabled' => filter_var($settings['interac_enabled'], FILTER_VALIDATE_BOOLEAN),
            'host' => $settings['interac_imap_host'],
            'port' => (int) $settings['interac_imap_port'],
            'encryption' => $settings['interac_imap_encryption'] ?: 'ssl',
            'username' => $settings['interac_imap_username'],
            'password' => $settings['interac_imap_password'],
            'folder' => $settings['interac_imap_folder'] ?: 'INBOX',
            'validate_cert' => filter_var($settings['interac_imap_validate_cert'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'mark_as_read' => filter_var($settings['interac_imap_mark_as_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'move_to' => $settings['interac_imap_move_to'] ?? '',
            'sender_filter' => $settings['interac_sender_filter'] ?? null,
        ];
    }
}
