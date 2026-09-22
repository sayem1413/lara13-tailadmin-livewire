<?php

it('groups digits using the Bangladeshi lakh/crore convention', function () {
    expect(formatPrice(999))->toBe('৳999.00')
        ->and(formatPrice(1234))->toBe('৳1,234.00')
        ->and(formatPrice(100000))->toBe('৳1,00,000.00')
        ->and(formatPrice(1000000))->toBe('৳10,00,000.00')
        ->and(formatPrice(12345678))->toBe('৳1,23,45,678.00');
});

it('puts the minus sign before the currency symbol for a negative amount', function () {
    expect(formatPrice(-500))->toBe('-৳500.00')
        ->and(formatPrice(-100000))->toBe('-৳1,00,000.00');
});

it('accepts a custom currency symbol', function () {
    expect(formatPrice(100000, '$'))->toBe('$1,00,000.00');
});

it('rounds to two decimal places', function () {
    expect(formatPrice(1234.567))->toBe('৳1,234.57');
});
