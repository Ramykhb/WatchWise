<?php

namespace Tests\Unit\Auth;

use App\Auth\Support\RateLimitAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RateLimitAddressTest extends TestCase
{
    /** @return array<string, array{?string, string}> */
    public static function addresses(): array
    {
        return [
            'IPv4 unchanged' => ['203.0.113.5', '203.0.113.5'],
            'IPv6 to its /64' => ['2001:db8:abcd:12:1:2:3:4', '2001:db8:abcd:12::/64'],
            'IPv6 written in full' => ['2001:0db8:abcd:0012:ffff:ffff:ffff:ffff', '2001:db8:abcd:12::/64'],
            'IPv6 loopback' => ['::1', '::/64'],
            'IPv4-mapped IPv6 as IPv4' => ['::ffff:203.0.113.5', '203.0.113.5'],
            'not an address unchanged' => ['unknown', 'unknown'],
            'missing' => [null, ''],
        ];
    }

    #[DataProvider('addresses')]
    public function test_it_maps_an_address_to_its_bucket(?string $ip, string $bucket): void
    {
        $this->assertSame($bucket, RateLimitAddress::of($ip));
    }

    public function test_two_addresses_of_one_64_share_a_bucket_and_another_64_does_not(): void
    {
        $this->assertSame(RateLimitAddress::of('2001:db8:1:2::a'), RateLimitAddress::of('2001:db8:1:2:ffff::b'));
        $this->assertNotSame(RateLimitAddress::of('2001:db8:1:2::a'), RateLimitAddress::of('2001:db8:1:3::a'));
    }
}
