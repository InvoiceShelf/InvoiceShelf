<?php

use InvoiceShelf\Modules\Registry;

afterEach(function () {
    Registry::flushDrivers();
});

test('registerDriver stores driver metadata under its type', function () {
    Registry::flushDrivers();

    Registry::registerDriver('exchange_rate', 'fake_provider', [
        'class' => 'FakeDriver',
        'label' => 'fake.label',
    ]);

    expect(Registry::driverMeta('exchange_rate', 'fake_provider'))
        ->toEqual([
            'class' => 'FakeDriver',
            'label' => 'fake.label',
        ]);
});

test('registerExchangeRateDriver delegates to registerDriver with the exchange_rate type', function () {
    Registry::flushDrivers();

    Registry::registerExchangeRateDriver('fake_provider', [
        'class' => 'FakeDriver',
        'label' => 'fake.label',
    ]);

    expect(Registry::driverMeta('exchange_rate', 'fake_provider'))
        ->not->toBeNull()
        ->and(Registry::allDrivers('exchange_rate'))
        ->toHaveKey('fake_provider');
});

test('allDrivers returns an empty array for unknown types', function () {
    Registry::flushDrivers();

    expect(Registry::allDrivers('nonexistent'))->toBe([]);
});

test('driverMeta returns null for unknown drivers', function () {
    Registry::flushDrivers();

    expect(Registry::driverMeta('exchange_rate', 'nonexistent'))->toBeNull();
});

test('flushDrivers clears all driver registrations', function () {
    Registry::registerExchangeRateDriver('a', ['class' => 'A', 'label' => 'a']);
    Registry::registerExchangeRateDriver('b', ['class' => 'B', 'label' => 'b']);

    Registry::flushDrivers();

    expect(Registry::allDrivers('exchange_rate'))->toBe([]);
});

test('flush does not clear driver registrations', function () {
    Registry::registerExchangeRateDriver('persists', [
        'class' => 'PersistDriver',
        'label' => 'persist.label',
    ]);

    Registry::flush();

    expect(Registry::driverMeta('exchange_rate', 'persists'))->not->toBeNull();
});
