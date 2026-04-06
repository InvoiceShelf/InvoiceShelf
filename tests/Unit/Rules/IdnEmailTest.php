<?php

use App\Rules\IdnEmail;
use Illuminate\Support\Facades\Validator;

test('accepts standard email addresses', function () {
    $validator = Validator::make(
        ['email' => 'user@example.com'],
        ['email' => [new IdnEmail]]
    );

    expect($validator->passes())->toBeTrue();
});

test('accepts IDN email with accented domain', function () {
    $validator = Validator::make(
        ['email' => 'michel@exempleé.fr'],
        ['email' => [new IdnEmail]]
    );

    expect($validator->passes())->toBeTrue();
});

test('accepts IDN email with umlaut domain', function () {
    $validator = Validator::make(
        ['email' => 'user@münchen.de'],
        ['email' => [new IdnEmail]]
    );

    expect($validator->passes())->toBeTrue();
});

test('rejects invalid email without at sign', function () {
    $validator = Validator::make(
        ['email' => 'notanemail'],
        ['email' => [new IdnEmail]]
    );

    expect($validator->passes())->toBeFalse();
});

test('rejects email with multiple at signs', function () {
    $validator = Validator::make(
        ['email' => 'user@@example.com'],
        ['email' => [new IdnEmail]]
    );

    expect($validator->passes())->toBeFalse();
});

test('accepts empty string without failing', function () {
    $validator = Validator::make(
        ['email' => ''],
        ['email' => ['nullable', new IdnEmail]]
    );

    expect($validator->passes())->toBeTrue();
});

test('accepts null without failing', function () {
    $validator = Validator::make(
        ['email' => null],
        ['email' => ['nullable', new IdnEmail]]
    );

    expect($validator->passes())->toBeTrue();
});
