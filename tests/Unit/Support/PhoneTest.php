<?php

use App\Support\Phone;

test('all supported Algerian mobile formats normalize to the same canonical form', function () {
    $input = [
        '0550 12 34 56',
        '+213 550 12 34 56',
        '00213550123456',
        '+213 (0)550 12 34 56',
        '213550123456',
        '0550123456',
    ];

    foreach ($input as $phone) {
        expect(Phone::normalize($phone))->toBe('213550123456');
    }
});

test('canonical phone helpers produce E.164, local and WhatsApp forms', function () {
    expect(Phone::toE164('0550 12 34 56'))->toBe('+213550123456')
        ->and(Phone::toLocal('+213 550 12 34 56'))->toBe('0550123456')
        ->and(Phone::whatsappUrl('0550 12 34 56'))->toBe('https://wa.me/213550123456');
});

test('landlines and invalid input are rejected', function () {
    expect(Phone::normalize('021 23 45 67'))->toBeNull()
        ->and(Phone::normalize('12345'))->toBeNull()
        ->and(Phone::normalize(''))->toBeNull()
        ->and(Phone::normalize(null))->toBeNull()
        ->and(Phone::normalize('+33 6 12 34 56 78'))->toBeNull();
});
