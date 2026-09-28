<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\RecurringCost;

/**
 * Which reference records a company's recurring templates use, so a
 * category, payment method or purchase tax a schedule still needs is not
 * deleted from under it. A company has few schedules, so their templates are
 * read and checked exactly.
 */
final class RecurringTemplates
{
    public static function useCategory(int $companyId, int $categoryId): bool
    {
        return self::any($companyId, function (array $template) use ($categoryId): bool {
            $ids = [$template['expense_category_id'] ?? null, ...array_column($template['items'] ?? [], 'expense_category_id')];

            return in_array($categoryId, array_map('intval', array_filter($ids)), true);
        });
    }

    public static function usePaymentMethod(int $companyId, int $paymentMethodId): bool
    {
        return self::any($companyId, fn (array $template): bool => (int) ($template['payment_method_id'] ?? 0) === $paymentMethodId);
    }

    public static function useTax(int $companyId, int $taxTypeId): bool
    {
        return self::any($companyId, function (array $template) use ($taxTypeId): bool {
            $ids = array_column($template['taxes'] ?? [], 'tax_type_id');

            foreach ($template['items'] ?? [] as $line) {
                array_push($ids, ...($line['tax_type_ids'] ?? []));
            }

            return in_array($taxTypeId, array_map('intval', $ids), true);
        });
    }

    /**
     * @param  callable(array): bool  $uses
     */
    private static function any(int $companyId, callable $uses): bool
    {
        foreach (RecurringCost::query()->forCompany($companyId)->select('id', 'template')->cursor() as $schedule) {
            if ($uses($schedule->template ?? [])) {
                return true;
            }
        }

        return false;
    }
}
