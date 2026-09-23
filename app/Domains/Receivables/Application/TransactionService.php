<?php

namespace App\Domains\Receivables\Application;

use App\Domains\Receivables\Models\Transaction;
use App\Support\PublicToken;

class TransactionService
{
    public function create(array $data): Transaction
    {
        $transaction = Transaction::create($data);
        $transaction->unique_hash = PublicToken::make();
        $transaction->save();

        return $transaction;
    }

    public function complete(Transaction $transaction): void
    {
        $transaction->status = Transaction::SUCCESS;
        $transaction->save();
    }

    public function fail(Transaction $transaction): void
    {
        $transaction->status = Transaction::FAILED;
        $transaction->save();
    }
}
