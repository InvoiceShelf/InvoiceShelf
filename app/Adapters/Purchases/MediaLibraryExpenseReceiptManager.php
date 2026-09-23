<?php

namespace App\Adapters\Purchases;

use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Data\StoredExpenseReceipt;
use App\Domains\Purchases\Models\Expense;
use App\Support\Media\SafeFileName;

class MediaLibraryExpenseReceiptManager implements ExpenseReceiptManager
{
    private const COLLECTION = 'receipts';

    public function attach(Expense $expense, PendingExpenseReceipt $receipt): void
    {
        $expense->addMedia($receipt->path)
            ->usingFileName(SafeFileName::from($receipt->fileName))
            ->toMediaCollection(self::COLLECTION);
    }

    public function replace(Expense $expense, PendingExpenseReceipt $receipt): void
    {
        $this->clear($expense);
        $this->attach($expense, $receipt);
    }

    public function attachBase64(
        Expense $expense,
        string $contents,
        string $fileName,
        bool $replaceExisting,
    ): void {
        if ($replaceExisting) {
            $this->clear($expense);
        }

        $expense->addMediaFromBase64($contents)
            ->usingFileName(SafeFileName::from($fileName))
            ->toMediaCollection(self::COLLECTION);
    }

    public function clear(Expense $expense): void
    {
        $expense->clearMediaCollection(self::COLLECTION);
    }

    public function first(Expense $expense): ?StoredExpenseReceipt
    {
        $media = $expense->getFirstMedia(self::COLLECTION);

        if (! $media) {
            return null;
        }

        return new StoredExpenseReceipt($media->getPath(), $media->file_name);
    }
}
