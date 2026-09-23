<?php

use App\Support\MinorUnits;

test('amounts in major units become minor units', function (mixed $major, ?int $minor) {
    expect(MinorUnits::fromMajor($major))->toBe($minor);
})->with([
    ['19.99', 1999],
    ['120', 12000],
    [12.5, 1250],
    [7, 700],
    ['-3.5', -350],
    ['0.05', 5],
    [' 42.10 ', 4210],
    ['1.234', null],
    ['1,50', null],
    ['abc', null],
    [null, null],
]);
