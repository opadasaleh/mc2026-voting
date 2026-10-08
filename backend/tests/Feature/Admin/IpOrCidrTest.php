<?php

namespace Tests\Feature\Admin;

use App\Rules\IpOrCidr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IpOrCidrTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function values(): array
    {
        return [
            'IPv4 address' => ['203.0.113.10', true],
            'IPv4 range' => ['203.0.113.0/24', true],
            'IPv6 address' => ['2001:db8:1::42', true],
            'IPv6 range' => ['2001:db8:1::/48', true],
            'IPv4 prefix too long' => ['10.0.0.0/33', false],
            'IPv6 prefix too long' => ['2001:db8::/129', false],
            'not a number prefix' => ['10.0.0.0/abc', false],
            'hostname' => ['venue.example.com', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('values')]
    public function test_values(string $value, bool $valid): void
    {
        $this->assertSame($valid, IpOrCidr::isValid($value));
    }
}
