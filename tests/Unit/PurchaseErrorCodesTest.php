<?php

use Symfony\Component\Finder\Finder;

/**
 * Purchasing reports its refusals as codes the SPA translates from
 * lang/en.json's `errors` section, as the other domains do.
 */
test('every purchasing error code has English text', function () {
    $errors = json_decode(file_get_contents(base_path('lang/en.json')), true)['errors'];
    $codes = [];

    foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
        // The message is the last argument of PurchaseInputs::ensure().
        preg_match_all("/ensure\\([^;]*?'(purchase_[a-z_]+)',?\\s*\\);/s", $file->getContents(), $matches);
        array_push($codes, ...$matches[1]);
    }

    $codes = array_values(array_unique($codes));

    expect($codes)->toHaveCount(32)
        ->and(array_values(array_diff($codes, array_keys($errors))))->toBe([]);
});
