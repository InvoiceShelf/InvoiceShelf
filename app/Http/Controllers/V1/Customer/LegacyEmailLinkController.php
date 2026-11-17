<?php

namespace App\Http\Controllers\V1\Customer;

use App\Facades\Hashids;
use App\Http\Controllers\Controller;
use App\Mail\LegacyEmailLinkMail;
use App\Models\CompanySetting;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\PublicToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Links in emails sent before 2.4.4.
 *
 * Those links carried a Hashids token derived from the email log's id, and
 * anyone can derive that token for any id (see PublicDocumentLinkTest), so
 * 2.4.4 gave every email log a random token and the old links stopped
 * working. Holding an old link proves nothing, so it must never open a
 * document again. It can still be honoured safely: the page offers to send
 * the email's current link to the address the email went to, so only that
 * inbox ever sees it. Link expiry applies exactly as it did to the old link.
 */
class LegacyEmailLinkController extends Controller
{
    /** The id of the newest email log that may have gone out with an old link. */
    public const CUTOFF_SETTING = 'legacy_email_link_max_id';

    /** Old tokens are always 20 characters long; random tokens are 40. */
    public const TOKEN_PATTERN = '[A-Za-z0-9]{20}';

    /** The document each route segment is about, and the route of its current link. */
    private const DOCUMENTS = [
        'invoices' => [Invoice::class, 'invoice'],
        'estimates' => [Estimate::class, 'estimate'],
        'payments' => [Payment::class, 'payment'],
    ];

    /** Resends allowed per email, per visitor and in total: [attempts, window in seconds]. */
    private const LIMITS = [
        'email' => [3, 86400],
        'visitor' => [10, 3600],
        'total' => [50, 86400],
    ];

    public function show(string $document, string $token): Response
    {
        $log = $this->resolve($document, $token);

        if ($log->isExpired()) {
            return $this->page($log, 'expired', 403);
        }

        return $this->page($log, 'offer');
    }

    public function resend(Request $request, string $document, string $token): Response
    {
        $log = $this->resolve($document, $token);

        if ($log->isExpired()) {
            return $this->page($log, 'expired', 403);
        }

        $recipients = $this->recipients($log);

        if ($recipients === []) {
            return $this->page($log, 'unavailable', 422);
        }

        if (! $this->allowResend($log, $request)) {
            return $this->page($log, 'limited', 429);
        }

        $url = route(self::DOCUMENTS[$document][1], ['email_log' => $this->currentToken($log)]);

        try {
            Mail::to($recipients)->send(new LegacyEmailLinkMail($log, $url));
        } catch (Throwable $exception) {
            report($exception);

            return $this->page($log, 'failed', 503);
        }

        return $this->page($log, 'sent');
    }

    /**
     * The email an old link belongs to, or a 404.
     *
     * Only a token Hashids produced counts (decode() rejects any other), only
     * for emails sent before random tokens existed, and only on the route for
     * the kind of document the email was about.
     */
    private function resolve(string $document, string $token): EmailLog
    {
        $ids = Hashids::connection(EmailLog::class)->decode($token);

        abort_unless(count($ids) === 1, 404);
        abort_unless($ids[0] <= (int) Setting::getSetting(self::CUTOFF_SETTING), 404);

        $log = EmailLog::query()->find($ids[0]);
        [$class] = self::DOCUMENTS[$document];

        abort_unless($log && $log->mailable instanceof $class, 404);

        return $log;
    }

    /**
     * The addresses the email went to.
     *
     * @return list<string>
     */
    private function recipients(EmailLog $log): array
    {
        $addresses = array_map('trim', preg_split('/[,;]/', (string) $log->to) ?: []);

        return array_values(array_filter(
            $addresses,
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /**
     * Count a resend against every limit, unless one of them is used up.
     */
    private function allowResend(EmailLog $log, Request $request): bool
    {
        $keys = [
            'email' => 'legacy-email-link:email:'.$log->id,
            'visitor' => 'legacy-email-link:visitor:'.$request->ip(),
            'total' => 'legacy-email-link:total',
        ];

        foreach ($keys as $limit => $key) {
            if (RateLimiter::tooManyAttempts($key, self::LIMITS[$limit][0])) {
                return false;
            }
        }

        foreach ($keys as $limit => $key) {
            RateLimiter::hit($key, self::LIMITS[$limit][1]);
        }

        return true;
    }

    /**
     * The token of the email's current link.
     *
     * The upgrade to 2.4.4 re-issued every token, so this only covers an
     * email it missed.
     */
    private function currentToken(EmailLog $log): string
    {
        if (strlen((string) $log->token) !== PublicToken::LENGTH) {
            $log->token = PublicToken::make();
            $log->save();
        }

        return $log->token;
    }

    /**
     * The page for an old link, in the company's language.
     */
    private function page(EmailLog $log, string $state, int $status = 200): Response
    {
        $company = $log->mailable->company;

        App::setLocale(CompanySetting::getSetting('language', $company->id) ?: config('app.locale'));

        return response()
            ->view('legacy-email-link', [
                'state' => $state,
                'company' => $company,
            ], $status)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
