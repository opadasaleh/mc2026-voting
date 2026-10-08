<?php

namespace Tests\Feature\Otp;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'Jordanian, local format' => ['0791234567', '+962791234567'],
            'Jordanian, international with spaces' => ['+962 79 123 4567', '+962791234567'],
            'Jordanian, 00 prefix' => ['00962791234567', '+962791234567'],
            'Jordanian, dashes' => ['079-123-4567', '+962791234567'],
            'foreign mobile' => ['+447911123456', '+447911123456'],
            'US number (fixed line or mobile)' => ['+12015550123', '+12015550123'],
            'Jordanian landline' => ['064123456', null],
            'too short' => ['12345', null],
            'not a number' => ['call me', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_the_blind_index_is_stable_and_depends_on_the_secret_key(): void
    {
        $hash = PhoneNumber::hash('+962791234567');

        $this->assertSame($hash, PhoneNumber::hash('+962791234567'));
        $this->assertSame(64, strlen($hash));
        $this->assertStringNotContainsString('791234567', $hash);

        config(['voting.phone.hash_key' => 'another-key']);
        $this->assertNotSame($hash, PhoneNumber::hash('+962791234567'));
    }
}
