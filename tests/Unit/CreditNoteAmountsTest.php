<?php

use App\Support\CreditNoteAmounts;

/**
 * Builds a snapshot the way production stores an invoice: every figure is a
 * pre-rounded integer derived the same way the client + DocumentTotals derive
 * it, and the base_* columns are the pre-rounded product of the amount and the
 * exchange rate.
 */
function creditSnapshot(array $options = []): array
{
    $taxPerItem = $options['tax_per_item'] ?? 'NO';
    $discountPerItem = $options['discount_per_item'] ?? 'NO';
    $taxIncluded = $options['tax_included'] ?? false;
    $rate = $options['exchange_rate'] ?? 1.0;
    $discountMode = $options['discount_mode'] ?? 'fixed';
    $lines = $options['lines'] ?? [
        11 => ['price' => 1234, 'quantity' => 3.0],
        12 => ['price' => 999, 'quantity' => 2.5],
    ];

    $perItemDiscount = $discountPerItem === 'YES';
    $perItemTax = $taxPerItem === 'YES';

    $items = [];
    $subTotal = 0;
    $itemTaxTotal = 0;
    $taxRowId = 100;

    foreach ($lines as $id => $line) {
        $amount = (int) round($line['price'] * $line['quantity']);

        $discount = 0;
        if ($perItemDiscount) {
            $discount = $discountMode === 'percentage'
                ? (int) round($amount * 10 / 100)
                : ($line['discount_fixed'] ?? 500);
        }

        $total = $amount - $discount;

        $taxes = [];
        $itemTax = 0;
        if ($perItemTax) {
            $percentage = (int) round($total * 7 / 100);
            $taxes[$taxRowId++] = ['amount' => $percentage, 'base_amount' => (int) round($percentage * $rate)];
            $taxes[$taxRowId++] = ['amount' => 150, 'base_amount' => (int) round(150 * $rate)];
            $itemTax = $percentage + 150;
        }

        $items[$id] = [
            'price' => $line['price'],
            'quantity' => (float) $line['quantity'],
            'discount_val' => $discount,
            'tax' => $itemTax,
            'total' => $total,
            'base_price' => (int) round($line['price'] * $rate),
            'base_discount_val' => (int) round($discount * $rate),
            'base_tax' => (int) round($itemTax * $rate),
            'base_total' => (int) round($total * $rate),
            'taxes' => $taxes,
        ];

        $subTotal += $total;
        $itemTaxTotal += $itemTax;
    }

    $discountVal = 0;
    if (! $perItemDiscount) {
        $discountVal = $discountMode === 'percentage'
            ? (int) round($subTotal * 5 / 100)
            : ($options['discount_fixed'] ?? 750);
    }

    $documentTaxes = [];
    $documentTaxTotal = 0;
    if (! $perItemTax) {
        $percentage = (int) round(($subTotal - $discountVal) * 7 / 100);
        $documentTaxes[900] = ['amount' => $percentage, 'base_amount' => (int) round($percentage * $rate)];
        $documentTaxes[901] = ['amount' => 150, 'base_amount' => (int) round(150 * $rate)];
        $documentTaxTotal = $percentage + 150;
    }

    $tax = $perItemTax ? $itemTaxTotal : $documentTaxTotal;
    $total = $taxIncluded ? $subTotal - $discountVal : $subTotal - $discountVal + $tax;

    return [
        'sub_total' => $subTotal,
        'discount_val' => $discountVal,
        'tax' => $tax,
        'total' => $total,
        'base_sub_total' => (int) round($subTotal * $rate),
        'base_discount_val' => (int) round($discountVal * $rate),
        'base_tax' => (int) round($tax * $rate),
        'base_total' => (int) round($total * $rate),
        'discount_per_item' => $discountPerItem,
        'tax_per_item' => $taxPerItem,
        'tax_included' => $taxIncluded,
        'items' => $items,
        'taxes' => $documentTaxes,
    ];
}

/**
 * The credited quantities that reverse the whole invoice.
 */
function creditFullQuantities(array $snapshot): array
{
    $quantities = [];

    foreach ($snapshot['items'] as $id => $item) {
        $quantities[$id] = CreditNoteAmounts::toHundredths($item['quantity']);
    }

    return $quantities;
}

function creditZeroQuantities(array $snapshot): array
{
    return array_fill_keys(array_keys($snapshot['items']), 0);
}

/**
 * Strict, recursive comparison that reports which field diverged.
 */
function expectSameAmounts(array $expected, array $actual, string $path = ''): void
{
    expect(array_keys($actual))->toBe(array_keys($expected));

    foreach ($expected as $key => $value) {
        $at = $path === '' ? (string) $key : $path.'.'.$key;

        if (is_array($value)) {
            expectSameAmounts($value, $actual[$key], $at);

            continue;
        }

        expect([$at => $actual[$key]])->toBe([$at => $value]);
    }
}

/**
 * The snapshot without the configuration flags, which is exactly the shape
 * cumulative() returns.
 */
function creditExpectedIdentity(array $snapshot): array
{
    unset($snapshot['discount_per_item'], $snapshot['tax_per_item'], $snapshot['tax_included']);

    return $snapshot;
}

dataset('credit note configurations', function () {
    foreach (['YES', 'NO'] as $taxPerItem) {
        foreach (['YES', 'NO'] as $discountPerItem) {
            foreach ([true, false] as $taxIncluded) {
                foreach (['fixed', 'percentage'] as $discountMode) {
                    foreach ([1.0, 1.37] as $rate) {
                        yield "tax_per_item=$taxPerItem discount_per_item=$discountPerItem tax_included=".
                            ($taxIncluded ? 'yes' : 'no')." discount=$discountMode rate=$rate" => [[
                                'tax_per_item' => $taxPerItem,
                                'discount_per_item' => $discountPerItem,
                                'tax_included' => $taxIncluded,
                                'discount_mode' => $discountMode,
                                'exchange_rate' => $rate,
                            ]];
                    }
                }
            }
        }
    }
});

test('crediting the full quantities reproduces the original invoice field for field', function (array $options) {
    $snapshot = creditSnapshot($options);

    $cumulative = CreditNoteAmounts::cumulative($snapshot, creditFullQuantities($snapshot));

    expectSameAmounts(creditExpectedIdentity($snapshot), $cumulative);
})->with('credit note configurations');

test('crediting nothing is zero in every field', function (array $options) {
    $snapshot = creditSnapshot($options);

    $cumulative = CreditNoteAmounts::cumulative($snapshot, creditZeroQuantities($snapshot));

    expect($cumulative['sub_total'])->toBe(0)
        ->and($cumulative['discount_val'])->toBe(0)
        ->and($cumulative['tax'])->toBe(0)
        ->and($cumulative['total'])->toBe(0)
        ->and($cumulative['base_sub_total'])->toBe(0)
        ->and($cumulative['base_discount_val'])->toBe(0)
        ->and($cumulative['base_tax'])->toBe(0)
        ->and($cumulative['base_total'])->toBe(0);

    foreach ($cumulative['items'] as $item) {
        expect($item['total'])->toBe(0)->and($item['tax'])->toBe(0)->and($item['base_total'])->toBe(0);
    }

    foreach ($cumulative['taxes'] as $tax) {
        expect($tax['amount'])->toBe(0)->and($tax['base_amount'])->toBe(0);
    }
})->with('credit note configurations');

test('a line credited one unit at a time sums back to the original invoice', function () {
    $snapshot = creditSnapshot([
        'lines' => [21 => ['price' => 333, 'quantity' => 3.0]],
        'discount_mode' => 'percentage',
        'exchange_rate' => 1.37,
    ]);

    $credits = [
        CreditNoteAmounts::forCredit($snapshot, [21 => 0], [21 => 100]),
        CreditNoteAmounts::forCredit($snapshot, [21 => 100], [21 => 200]),
        CreditNoteAmounts::forCredit($snapshot, [21 => 200], [21 => 300]),
    ];

    $documentFields = [
        'sub_total', 'discount_val', 'tax', 'total',
        'base_sub_total', 'base_discount_val', 'base_tax', 'base_total',
    ];

    foreach ($documentFields as $field) {
        $sum = array_sum(array_column($credits, $field));

        expect([$field => $sum])->toBe([$field => $snapshot[$field]]);
    }

    foreach (array_keys($snapshot['taxes']) as $taxId) {
        $amount = array_sum(array_map(fn ($credit) => $credit['taxes'][$taxId]['amount'], $credits));
        $baseAmount = array_sum(array_map(fn ($credit) => $credit['taxes'][$taxId]['base_amount'], $credits));

        expect(["tax.$taxId.amount" => $amount])->toBe(["tax.$taxId.amount" => $snapshot['taxes'][$taxId]['amount']])
            ->and(["tax.$taxId.base_amount" => $baseAmount])
            ->toBe(["tax.$taxId.base_amount" => $snapshot['taxes'][$taxId]['base_amount']]);
    }

    $quantity = array_sum(array_map(fn ($credit) => $credit['items'][21]['quantity'], $credits));
    $lineTotal = array_sum(array_map(fn ($credit) => $credit['items'][21]['total'], $credits));

    expect($quantity)->toBe(3.0)
        ->and($lineTotal)->toBe($snapshot['items'][21]['total'])
        ->and($credits[0]['items'][21]['source_invoice_item_id'])->toBe(21)
        ->and($credits[0]['items'][21]['price'])->toBe(333);
});

test('per-item tax rows also telescope back to the original amounts', function () {
    $snapshot = creditSnapshot([
        'tax_per_item' => 'YES',
        'discount_per_item' => 'YES',
        'discount_mode' => 'percentage',
        'lines' => [31 => ['price' => 333, 'quantity' => 3.0]],
        'exchange_rate' => 1.37,
    ]);

    $credits = [
        CreditNoteAmounts::forCredit($snapshot, [31 => 0], [31 => 100]),
        CreditNoteAmounts::forCredit($snapshot, [31 => 100], [31 => 200]),
        CreditNoteAmounts::forCredit($snapshot, [31 => 200], [31 => 300]),
    ];

    foreach (array_keys($snapshot['items'][31]['taxes']) as $taxId) {
        $amount = array_sum(array_map(fn ($credit) => $credit['items'][31]['taxes'][$taxId]['amount'], $credits));
        $baseAmount = array_sum(array_map(fn ($credit) => $credit['items'][31]['taxes'][$taxId]['base_amount'], $credits));

        expect(["item.tax.$taxId.amount" => $amount])
            ->toBe(["item.tax.$taxId.amount" => $snapshot['items'][31]['taxes'][$taxId]['amount']])
            ->and(["item.tax.$taxId.base_amount" => $baseAmount])
            ->toBe(["item.tax.$taxId.base_amount" => $snapshot['items'][31]['taxes'][$taxId]['base_amount']]);
    }

    expect(array_sum(array_column($credits, 'tax')))->toBe($snapshot['tax'])
        ->and(array_sum(array_column($credits, 'total')))->toBe($snapshot['total'])
        ->and(array_sum(array_column($credits, 'base_tax')))->toBe($snapshot['base_tax'])
        ->and(array_sum(array_column($credits, 'discount_val')))->toBe($snapshot['discount_val']);
});

test('the order the chunks are credited in does not change the result', function () {
    $snapshot = creditSnapshot([
        'lines' => [41 => ['price' => 333, 'quantity' => 3.0]],
        'discount_mode' => 'percentage',
        'exchange_rate' => 1.37,
    ]);

    $oneThenTwo = [
        CreditNoteAmounts::forCredit($snapshot, [41 => 0], [41 => 100]),
        CreditNoteAmounts::forCredit($snapshot, [41 => 100], [41 => 300]),
    ];

    $twoThenOne = [
        CreditNoteAmounts::forCredit($snapshot, [41 => 0], [41 => 200]),
        CreditNoteAmounts::forCredit($snapshot, [41 => 200], [41 => 300]),
    ];

    $atOnce = [CreditNoteAmounts::forCredit($snapshot, [41 => 0], [41 => 300])];

    $documentFields = [
        'sub_total', 'discount_val', 'tax', 'total',
        'base_sub_total', 'base_discount_val', 'base_tax', 'base_total',
    ];

    foreach ($documentFields as $field) {
        $a = array_sum(array_column($oneThenTwo, $field));
        $b = array_sum(array_column($twoThenOne, $field));
        $c = array_sum(array_column($atOnce, $field));

        expect([$field => $a])->toBe([$field => $c])
            ->and([$field => $b])->toBe([$field => $c]);
    }
});

test('fractional quantities are exact in hundredths', function () {
    $snapshot = creditSnapshot([
        'lines' => [51 => ['price' => 4500, 'quantity' => 7.25]],
        'discount_mode' => 'percentage',
    ]);

    expect($snapshot['sub_total'])->toBe(32625);

    $credit = CreditNoteAmounts::forCredit($snapshot, [51 => 0], [51 => 250]);

    expect($credit['items'][51]['quantity'])->toBe(2.5)
        ->and($credit['items'][51]['total'])->toBe(11250)
        ->and($credit['sub_total'])->toBe(11250);

    $rest = CreditNoteAmounts::forCredit($snapshot, [51 => 250], [51 => 725]);

    expect($rest['items'][51]['quantity'])->toBe(4.75)
        ->and($credit['sub_total'] + $rest['sub_total'])->toBe($snapshot['sub_total'])
        ->and($credit['total'] + $rest['total'])->toBe($snapshot['total'])
        ->and($credit['tax'] + $rest['tax'])->toBe($snapshot['tax']);
});

test('zero sub totals, discounts and taxes never divide by zero', function () {
    $snapshot = [
        'sub_total' => 0,
        'discount_val' => 0,
        'tax' => 0,
        'total' => 0,
        'base_sub_total' => 0,
        'base_discount_val' => 0,
        'base_tax' => 0,
        'base_total' => 0,
        'discount_per_item' => 'YES',
        'tax_per_item' => 'NO',
        'tax_included' => false,
        'items' => [
            61 => [
                'price' => 0,
                'quantity' => 2.0,
                'discount_val' => 0,
                'tax' => 0,
                'total' => 0,
                'base_price' => 0,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => 0,
                'taxes' => [700 => ['amount' => 0, 'base_amount' => 0]],
            ],
            62 => [
                'price' => 1000,
                'quantity' => 0.0,
                'discount_val' => 0,
                'tax' => 0,
                'total' => 0,
                'base_price' => 1000,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => 0,
                'taxes' => [],
            ],
        ],
        'taxes' => [800 => ['amount' => 0, 'base_amount' => 0]],
    ];

    set_error_handler(function (int $severity, string $message) {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        $credit = CreditNoteAmounts::forCredit($snapshot, [61 => 0, 62 => 0], [61 => 200, 62 => 0]);
    } finally {
        restore_error_handler();
    }

    expect($credit['sub_total'])->toBe(0)
        ->and($credit['discount_val'])->toBe(0)
        ->and($credit['tax'])->toBe(0)
        ->and($credit['total'])->toBe(0)
        ->and($credit['base_sub_total'])->toBe(0)
        ->and($credit['base_discount_val'])->toBe(0)
        ->and($credit['base_tax'])->toBe(0)
        ->and($credit['base_total'])->toBe(0)
        ->and($credit['taxes'][800])->toBe(['amount' => 0, 'base_amount' => 0])
        ->and($credit['items'][61]['total'])->toBe(0)
        ->and($credit['items'][61]['quantity'])->toBe(2.0)
        ->and($credit['items'])->not->toHaveKey(62);
});

test('every base amount equals its counterpart at exchange rate 1', function () {
    $snapshot = creditSnapshot([
        'tax_per_item' => 'YES',
        'discount_per_item' => 'YES',
        'discount_mode' => 'percentage',
        'exchange_rate' => 1.0,
    ]);

    $credit = CreditNoteAmounts::forCredit($snapshot, [11 => 0, 12 => 0], [11 => 100, 12 => 150]);

    expect($credit['base_sub_total'])->toBe($credit['sub_total'])
        ->and($credit['base_discount_val'])->toBe($credit['discount_val'])
        ->and($credit['base_tax'])->toBe($credit['tax'])
        ->and($credit['base_total'])->toBe($credit['total']);

    foreach ($credit['items'] as $item) {
        expect($item['base_price'])->toBe($item['price'])
            ->and($item['base_discount_val'])->toBe($item['discount_val'])
            ->and($item['base_tax'])->toBe($item['tax'])
            ->and($item['base_total'])->toBe($item['total']);

        foreach ($item['taxes'] as $tax) {
            expect($tax['base_amount'])->toBe($tax['amount']);
        }
    }
});

test('halves round away from zero rather than truncating', function () {
    $snapshot = creditSnapshot([
        'discount_per_item' => 'YES',
        'lines' => [71 => ['price' => 333, 'quantity' => 3.0, 'discount_fixed' => 5]],
    ]);

    // 333 * 1.5 = 499.5: truncation would credit 499 cents.
    $half = CreditNoteAmounts::cumulative($snapshot, [71 => 150]);

    expect($half['items'][71]['total'] + $half['items'][71]['discount_val'])->toBe(500);

    // The 5 cent line discount at ratio 1/2 is 2.5: truncation would credit 2.
    expect($half['items'][71]['discount_val'])->toBe(3);
});

test('a first credit note against an untouched invoice equals the cumulative credit', function () {
    $snapshot = creditSnapshot([
        'tax_per_item' => 'YES',
        'discount_per_item' => 'YES',
        'discount_mode' => 'percentage',
        'exchange_rate' => 1.37,
    ]);

    $after = [11 => 200, 12 => 0];

    $credit = CreditNoteAmounts::forCredit($snapshot, [11 => 0, 12 => 0], $after);
    $cumulative = CreditNoteAmounts::cumulative($snapshot, $after);

    $documentFields = [
        'sub_total', 'discount_val', 'tax', 'total',
        'base_sub_total', 'base_discount_val', 'base_tax', 'base_total',
    ];

    foreach ($documentFields as $field) {
        expect([$field => $credit[$field]])->toBe([$field => $cumulative[$field]]);
    }

    expect($credit['taxes'])->toBe($cumulative['taxes'])
        ->and(array_keys($credit['items']))->toBe([11]);

    foreach ($credit['items'] as $itemId => $item) {
        foreach (['price', 'base_price', 'quantity', 'discount_val', 'tax', 'total', 'base_discount_val', 'base_tax', 'base_total', 'taxes'] as $field) {
            expect([$field => $item[$field]])->toBe([$field => $cumulative['items'][$itemId][$field]]);
        }
    }
});

test('a full reversal built from forCredit matches the original invoice', function (array $options) {
    $snapshot = creditSnapshot($options);

    $credit = CreditNoteAmounts::forCredit($snapshot, creditZeroQuantities($snapshot), creditFullQuantities($snapshot));

    expect($credit['sub_total'])->toBe($snapshot['sub_total'])
        ->and($credit['discount_val'])->toBe($snapshot['discount_val'])
        ->and($credit['tax'])->toBe($snapshot['tax'])
        ->and($credit['total'])->toBe($snapshot['total'])
        ->and($credit['base_sub_total'])->toBe($snapshot['base_sub_total'])
        ->and($credit['base_discount_val'])->toBe($snapshot['base_discount_val'])
        ->and($credit['base_tax'])->toBe($snapshot['base_tax'])
        ->and($credit['base_total'])->toBe($snapshot['base_total'])
        ->and($credit['taxes'])->toBe($snapshot['taxes']);
})->with('credit note configurations');
