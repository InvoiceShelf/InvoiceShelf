<?php

/**
 * The report templates print whatever key they are given when it is missing, and
 * nothing else looks at them: the expenses report shipped table headings reading
 * "expenses.date" / "expenses.note" / "expenses.amount" because those keys were
 * never in lang/en.json. Only en.json is asserted on, it is the only locale file
 * edited by hand (the rest come from Crowdin).
 *
 * @return list<string>
 */
function reportTemplateTranslationKeys(string $template): array
{
    preg_match_all(
        '/(?:@lang|__|trans)\(\s*[\'"]([^\'"]+)[\'"]/',
        file_get_contents(resource_path("views/app/pdf/reports/{$template}.blade.php")),
        $matches
    );

    return array_values(array_unique($matches[1]));
}

test('every translation key a report template uses exists in english', function (string $template) {
    $english = json_decode(file_get_contents(lang_path('en.json')), true);
    $keys = reportTemplateTranslationKeys($template);

    $missing = array_values(array_filter(
        $keys,
        // array_key_exists rather than toHaveKey: en.json has nested sections and
        // dot notation would resolve a dotted key into one of them.
        fn (string $key) => ! array_key_exists($key, $english)
    ));

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
})->with([
    'expenses',
    'profit-loss',
    'purchases',
    'sales-customers',
    'sales-items',
    'tax-summary',
]);

test('the expenses report headings are translated rather than printing their keys', function () {
    $english = json_decode(file_get_contents(lang_path('en.json')), true);

    expect(reportTemplateTranslationKeys('expenses'))
        ->toContain('pdf_expense_date_label')
        ->toContain('pdf_expense_note_label')
        ->toContain('pdf_expense_amount_label')
        ->and($english['pdf_expense_date_label'])->toBe('Date')
        ->and($english['pdf_expense_note_label'])->toBe('Note')
        ->and($english['pdf_expense_amount_label'])->toBe('Amount');
});
