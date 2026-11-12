<?php

declare(strict_types=1);

namespace App\Services\Support;

use InvalidArgumentException;

class PhoneNormalizer
{
    /**
     * Normalize a Ghanaian phone number to E.164 format (+233XXXXXXXXX).
     *
     * Supports:
     * - 0XXXXXXXXX (10 digits starting with 0)
     * - 233XXXXXXXXX (12 digits starting with 233)
     * - +233XXXXXXXXX (13 chars starting with +233)
     *
     * Note: We intentionally avoid hardcoding network prefixes (e.g. 024, 054)
     * because mobile number portability (MNP) is widely used in Ghana.
     */
    public static function normalize(string $phone): string
    {
        // Remove whitespace, hyphens, parentheses, dots
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', trim($phone)) ?? '';

        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        }

        // 10 digits starting with 0 (e.g. 0241234567)
        if (preg_match('/^0([0-9]{9})$/', $cleaned, $matches)) {
            return '+233' . $matches[1];
        }

        // 12 digits starting with 233 (e.g. 233241234567)
        if (preg_match('/^233([0-9]{9})$/', $cleaned, $matches)) {
            return '+233' . $matches[1];
        }

        // 9 digits (e.g. 241234567)
        if (preg_match('/^([0-9]{9})$/', $cleaned, $matches)) {
            return '+233' . $matches[1];
        }

        throw new InvalidArgumentException(
            "Invalid Ghanaian phone number format. Expected format: 024XXXXXXX or +23324XXXXXXX."
        );
    }

    /**
     * Check if a phone string is a valid Ghanaian mobile number.
     */
    public static function isValid(string $phone): bool
    {
        try {
            self::normalize($phone);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Convert to 10-digit national format (e.g. 0241234567)
     */
    public static function toNational(string $phone): string
    {
        $e164 = self::normalize($phone);
        // +233XXXXXXXXX -> 0XXXXXXXXX
        return '0' . substr($e164, 4);
    }

    /**
     * Convert to 12-digit international format without leading plus (e.g. 233241234567)
     */
    public static function toInternationalWithoutPlus(string $phone): string
    {
        $e164 = self::normalize($phone);
        return substr($e164, 1);
    }
}
