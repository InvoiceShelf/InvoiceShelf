<?php

namespace App\Domains\Sales\Contracts;

interface QuoteEmailSender
{
    /** @param array<string, mixed> $data */
    public function send(array $data): void;

    public function viewed(array $data, string $recipient): void;
}
