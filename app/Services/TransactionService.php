<?php

namespace App\Services;

use App\Facades\Hashids;
use App\Models\Transaction;

class TransactionService
{
    public function create(array $data): Transaction
    {
        $transaction = Transaction::create($data);
        $transaction->unique_hash = Hashids::connection(Transaction::class)->encode($transaction->id);
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
