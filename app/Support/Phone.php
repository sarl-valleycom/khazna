<?php

namespace App\Support;

/**
 * Algerian mobile phone helpers. Numbers are stored canonically as
 * 213XXXXXXXXX (digits only), which is also the form wa.me needs.
 */
final class Phone
{
    public const ALGERIA_CODE = '213';

    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);
        $phone = preg_replace('/^p:/i', '', $phone) ?? $phone;
        $phone = preg_replace('/^(\+|00)?213\s*\(\s*0\s*\)\s*/', '213', $phone) ?? $phone;

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, self::ALGERIA_CODE)) {
            $national = substr($digits, strlen(self::ALGERIA_CODE));

            if (str_starts_with($national, '0')) {
                $national = substr($national, 1);
            }
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            return null;
        }

        if (preg_match('/^[567]\d{8}$/', $national) !== 1) {
            return null;
        }

        return self::ALGERIA_CODE.$national;
    }

    public static function toE164(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : '+'.$normalized;
    }

    public static function toLocal(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : '0'.substr($normalized, strlen(self::ALGERIA_CODE));
    }

    public static function whatsappUrl(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : 'https://wa.me/'.$normalized;
    }
}
