<?php

use App\Support\DocumentTaxes;
use App\Support\DocumentTotals;

/*
 * The golden cases come from the document form's own helpers, run under Node
 * by tests/Fixtures/document-math/generate.ts. The document-level cases below
 * cover what lives in the form's components instead.
 */

function documentMathCases(string $set): array
{
    static $cases;

    $cases ??= json_decode(file_get_contents(base_path('tests/Fixtures/document-math/cases.json')), true);

    return $cases[$set];
}

function rate(float|int|null $percent, bool $compound = false, string $calculation = 'percentage', ?int $fixed = null, int $id = 1): array
{
    return [
        'tax_type_id' => $id,
        'name' => "Tax {$id}",
        'percent' => $percent,
        'calculation_type' => $calculation,
        'fixed_amount' => $fixed,
        'compound_tax' => $compound,
    ];
}

test('line subtotals match the form', function () {
    foreach (documentMathCases('subtotals') as [$price, $quantity, $expected]) {
        expect(DocumentTaxes::itemSubtotal($price, $quantity))->toBe($expected, "{$price} x {$quantity}");
    }
});

test('line discounts match the form', function () {
    foreach (documentMathCases('itemDiscounts') as [$subTotal, $discount, $type, $expected]) {
        expect(DocumentTaxes::itemDiscountVal($subTotal, $discount, $type))->toBe($expected, "{$discount} {$type} of {$subTotal}");
    }
});

test('tax amounts match the form', function () {
    foreach (documentMathCases('taxes') as [$base, $percent, $fixed, $type, $included, $compound, $simple, $expected]) {
        expect(DocumentTaxes::taxAmount($base, $percent, $fixed, $type, $included, $compound, $simple))
            ->toBe($expected, json_encode(compact('base', 'percent', 'fixed', 'type', 'included', 'compound', 'simple')));
    }
});

test('two-decimal proportions round the way toFixed does, ties included', function () {
    foreach (documentMathCases('proportions') as [$line, $all, $expected]) {
        expect(DocumentTaxes::toFixed2($line / $all))->toBe((float) $expected, "{$line} / {$all}");
    }
});

test('rounding is Math.round', function () {
    foreach (documentMathCases('rounds') as [$value, $expected]) {
        expect(DocumentTaxes::round($value))->toBe($expected, (string) $value);
    }
});

test('a document tax is charged on the discounted subtotal', function () {
    $fixedOff = DocumentTaxes::compose([['price' => 12000, 'quantity' => 10]], [rate(20)], 5, 'fixed', false, false, false);
    $percentOff = DocumentTaxes::compose([['price' => 12000, 'quantity' => 10]], [rate(20)], 10, 'percentage', false, false, false);

    expect($fixedOff['discount_val'])->toBe(500)
        ->and($fixedOff['taxes'][0]['amount'])->toBe(23900)
        ->and($fixedOff['total'])->toBe(143400)
        ->and($percentOff['discount_val'])->toBe(12000)
        ->and($percentOff['taxes'][0]['amount'])->toBe(21600)
        ->and($percentOff['total'])->toBe(129600);
});

test('an inclusive simple tax is backed out and adds nothing to the total', function () {
    $sums = DocumentTaxes::compose([['price' => 120000, 'quantity' => 1]], [rate(20)], 0, 'fixed', false, false, true);

    expect($sums['taxes'][0]['amount'])->toBe(20000)
        ->and($sums['tax'])->toBe(20000)
        ->and($sums['total'])->toBe(120000);
});

test('a compound tax is charged on the subtotal plus the simple taxes, in either order', function () {
    $compoundFirst = DocumentTaxes::compose(
        [['price' => 100000, 'quantity' => 1]],
        [rate(5, true, id: 2), rate(10, id: 1)],
        0, 'fixed', false, false, false,
    );

    expect(array_column($compoundFirst['taxes'], 'tax_type_id'))->toBe([2, 1])
        ->and(array_column($compoundFirst['taxes'], 'amount'))->toBe([5500, 10000])
        ->and($compoundFirst['total'])->toBe(115500);
});

test('an inclusive compound tax sits on top of the gross amount', function () {
    $sums = DocumentTaxes::compose(
        [['price' => 100000, 'quantity' => 1]],
        [rate(10, id: 1), rate(5, true, id: 2)],
        0, 'fixed', false, false, true,
    );

    expect(array_column($sums['taxes'], 'amount'))->toBe([9091, 5000])
        ->and($sums['tax'])->toBe(14091)
        ->and($sums['total'])->toBe(105000);
});

test('a fixed tax is charged once per row, whatever the quantity', function () {
    $perDocument = DocumentTaxes::compose([['price' => 1000, 'quantity' => 3]], [rate(null, calculation: 'fixed', fixed: 250)], 0, 'fixed', false, false, false);
    $perLine = DocumentTaxes::compose(
        [['price' => 1000, 'quantity' => 3, 'taxes' => [rate(null, calculation: 'fixed', fixed: 250)]], ['price' => 500, 'quantity' => 2, 'taxes' => [rate(null, calculation: 'fixed', fixed: 250)]]],
        [], 0, 'fixed', true, false, false,
    );

    expect($perDocument['tax'])->toBe(250)
        ->and(array_column($perLine['lines'], 'tax'))->toBe([250, 250])
        ->and($perLine['total'])->toBe(4500);
});

test('a fixed tax with a simple one widens the base of a compound tax', function () {
    $sums = DocumentTaxes::compose(
        [['price' => 10000, 'quantity' => 1]],
        [rate(null, calculation: 'fixed', fixed: 1000, id: 1), rate(10, true, id: 2)],
        0, 'fixed', false, false, false,
    );

    expect(array_column($sums['taxes'], 'amount'))->toBe([1000, 1100]);
});

test('line taxes are charged on the line net of its own discount', function () {
    $sums = DocumentTaxes::compose(
        [
            ['price' => 5000, 'quantity' => 2, 'discount' => 10, 'discount_type' => 'percentage', 'taxes' => [rate(20)]],
            ['price' => 1000, 'quantity' => 1, 'discount' => 50, 'discount_type' => 'fixed', 'taxes' => [rate(20)]],
        ],
        [], 0, 'fixed', true, true, false,
    );

    expect($sums['lines'][0])->toMatchArray(['sub_total' => 10000, 'discount_val' => 1000, 'total' => 9000, 'tax' => 1800])
        // A fixed line discount never exceeds the line.
        ->and($sums['lines'][1])->toMatchArray(['sub_total' => 1000, 'discount_val' => 1000, 'total' => 0, 'tax' => 0])
        ->and($sums['discount_val'])->toBe(0)
        ->and($sums['total'])->toBe(10800);
});

test('a document discount is shared out to line taxes by a two-decimal proportion', function () {
    $sums = DocumentTaxes::compose(
        array_fill(0, 3, ['price' => 10000, 'quantity' => 1, 'taxes' => [rate(10)]]),
        [], 10, 'fixed', true, false, false,
    );

    // Each line takes 0.33 of the 10.00 off, so 9.90 of it reaches the taxes.
    expect(array_column($sums['lines'], 'tax'))->toBe([967, 967, 967])
        ->and($sums['discount_val'])->toBe(1000)
        ->and($sums['total'])->toBe(30000 - 1000 + 2901);
});

test('a line share that ties at two decimals rounds up', function () {
    $sums = DocumentTaxes::compose(
        [['price' => 1000, 'quantity' => 1, 'taxes' => [rate(100)]], ['price' => 7000, 'quantity' => 1, 'taxes' => [rate(100)]]],
        [], 1, 'fixed', true, false, false,
    );

    // 1000 / 8000 is 0.125, which toFixed(2) makes 0.13 and sprintf 0.12.
    expect($sums['lines'][0]['tax'])->toBe(1000 - 13)
        ->and($sums['lines'][1]['tax'])->toBe(7000 - 88);
});

test('a percentage document discount is shared out by the same proportion', function () {
    $sums = DocumentTaxes::compose(
        [['price' => 10000, 'quantity' => 1, 'taxes' => [rate(10)]], ['price' => 30000, 'quantity' => 1, 'taxes' => [rate(10)]]],
        [], 10, 'percentage', true, false, false,
    );

    expect(array_column($sums['lines'], 'tax'))->toBe([900, 2700])
        ->and($sums['discount_val'])->toBe(4000)
        ->and($sums['total'])->toBe(39600);
});

test('a per-line discount setting ignores a document discount', function () {
    $sums = DocumentTaxes::compose([['price' => 10000, 'quantity' => 1, 'taxes' => [rate(10)]]], [], 50, 'fixed', true, true, false);

    expect($sums['discount_val'])->toBe(0)
        ->and($sums['total'])->toBe(11000);
});

test('per-line taxes leave the document tax rows empty', function () {
    $sums = DocumentTaxes::compose([['price' => 10000, 'quantity' => 1, 'taxes' => [rate(10)]]], [rate(20)], 0, 'fixed', true, false, false);

    expect($sums['taxes'])->toBe([])
        ->and($sums['tax'])->toBe(1000);
});

test('negative lines round half toward positive infinity', function () {
    $sums = DocumentTaxes::compose([['price' => 5, 'quantity' => -0.5]], [], 0, 'fixed', false, false, false);

    expect($sums['lines'][0]['sub_total'])->toBe(-2);
});

test('the composed sums agree with the stored ones', function () {
    $lines = [
        ['price' => 1999, 'quantity' => 3, 'discount' => 5, 'discount_type' => 'percentage', 'taxes' => [rate(8.5), rate(2, true, id: 2)]],
        ['price' => 12345, 'quantity' => 0.5, 'discount' => 1.5, 'discount_type' => 'fixed', 'taxes' => [rate(8.5)]],
    ];

    $sums = DocumentTaxes::compose($lines, [], 0, 'fixed', true, true, false);

    $items = array_map(fn (array $line, array $composed) => [
        'price' => $line['price'],
        'quantity' => $line['quantity'],
        'discount_val' => $composed['discount_val'],
        'taxes' => $composed['taxes'],
    ], $lines, $sums['lines']);

    expect(DocumentTotals::compute($items, [], $sums['discount_val'], 'YES', false, 'YES'))
        ->toBe(['sub_total' => $sums['sub_total'], 'tax' => $sums['tax'], 'total' => $sums['total']]);
});
