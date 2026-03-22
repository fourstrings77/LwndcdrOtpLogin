<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Otp;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * DI factory for the OTP verification rate limiter.
 *
 * Policy: 5 attempts per 15 minutes per client IP.
 * Uses the Symfony RateLimiter (fixed-window) backed by Shopware's app cache.
 */
class OtpRateLimiterFactory
{
    public static function createOtpVerifyLimiter(CacheItemPoolInterface $cache): RateLimiterFactory
    {
        return new RateLimiterFactory(
            [
                'id' => 'lwndcdr_otp_verify',
                'policy' => 'fixed_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
            new CacheStorage($cache),
        );
    }
}
