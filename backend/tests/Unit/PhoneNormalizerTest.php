<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Support\PhoneNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_standard_10_digit_national_phone(): void
    {
        $normalized = PhoneNormalizer::normalize('0244123456');
        $this->assertSame('+233244123456', $normalized);
    }

    public function test_normalizes_12_digit_with_country_code(): void
    {
        $normalized = PhoneNormalizer::normalize('233244123456');
        $this->assertSame('+233244123456', $normalized);
    }

    public function test_normalizes_phone_with_plus_prefix(): void
    {
        $normalized = PhoneNormalizer::normalize('+233244123456');
        $this->assertSame('+233244123456', $normalized);
    }

    public function test_normalizes_phone_with_spaces_and_hyphens(): void
    {
        $normalized = PhoneNormalizer::normalize('024 412-3456');
        $this->assertSame('+233244123456', $normalized);
    }

    public function test_converts_to_national_format(): void
    {
        $this->assertSame('0244123456', PhoneNormalizer::toNational('+233244123456'));
        $this->assertSame('0244123456', PhoneNormalizer::toNational('233244123456'));
        $this->assertSame('0244123456', PhoneNormalizer::toNational('0244123456'));
    }

    public function test_converts_to_international_without_plus(): void
    {
        $this->assertSame('233244123456', PhoneNormalizer::toInternationalWithoutPlus('0244123456'));
    }

    public function test_is_valid_returns_true_for_valid_numbers(): void
    {
        $this->assertTrue(PhoneNormalizer::isValid('0201234567'));
        $this->assertTrue(PhoneNormalizer::isValid('+233551234567'));
        $this->assertTrue(PhoneNormalizer::isValid('233271234567'));
    }

    public function test_is_valid_returns_false_for_invalid_numbers(): void
    {
        $this->assertFalse(PhoneNormalizer::isValid('12345'));
        $this->assertFalse(PhoneNormalizer::isValid('024412345')); // 9 digits total
        $this->assertFalse(PhoneNormalizer::isValid('+15551234567')); // US number
        $this->assertFalse(PhoneNormalizer::isValid('abcdefghij'));
    }

    public function test_throws_exception_on_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhoneNormalizer::normalize('024');
    }
}

