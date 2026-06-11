<?php

use App\Support\SafeOrderBy;
use Illuminate\Support\Facades\DB;

test('rejects sql expressions in the order-by field', function () {
    $sql = strtolower(
        SafeOrderBy::apply(DB::table('invoices'), '(CASE WHEN (SELECT 1)=1 THEN id ELSE total END)', 'asc')->toSql()
    );

    expect($sql)->toContain('order by')
        ->and($sql)->not->toContain('case')
        ->and($sql)->not->toContain('select 1');
});

test('allows a plain column', function () {
    $sql = strtolower(SafeOrderBy::apply(DB::table('invoices'), 'invoice_date', 'asc')->toSql());

    expect($sql)->toContain('order by')->and($sql)->toContain('invoice_date');
});

test('allows a table-qualified column so joined/aliased sorts keep working', function () {
    $sql = strtolower(SafeOrderBy::apply(DB::table('estimates'), 'customers.name', 'asc')->toSql());

    expect($sql)->toContain('customers')->and($sql)->toContain('name');
});

test('clamps the direction to asc/desc', function () {
    $sql = strtolower(SafeOrderBy::apply(DB::table('invoices'), 'id', 'asc); drop table users; --')->toSql());

    expect($sql)->toContain('order by')->and($sql)->not->toContain('drop table');
});
