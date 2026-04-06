<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use DateInterval;
use DateTimeImmutable;
use yii\inertia\OnceProp;

/**
 * Unit tests for {@see OnceProp}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class OncePropTest extends TestCase
{
    public function testAsSetsCacheKey(): void
    {
        $once = (new OnceProp(fn() => []))
            ->as('my-key');

        self::assertSame(
            'my-key',
            $once->getKey(),
            'Should set the custom cache key.',
        );
    }

    public function testDefaultExpiresAtIsNull(): void
    {
        $once = new OnceProp(fn() => []);

        self::assertNull(
            $once->getExpiresAtMs(),
            "Default expiresAtMs should be 'null'.",
        );
    }

    public function testDefaultKeyIsNull(): void
    {
        $once = new OnceProp(fn() => []);

        self::assertNull(
            $once->getKey(),
            "Default key should be 'null'.",
        );
    }

    public function testGetCallbackReturnsClosure(): void
    {
        $callback = fn() => 'value';

        $once = new OnceProp($callback);

        self::assertSame(
            $callback,
            $once->getCallback(),
            'Should return the constructor Closure.',
        );
    }

    public function testReturnNewInstanceWhenSettingAttribute(): void
    {
        $once = new OnceProp(fn() => []);

        self::assertNotSame(
            $once,
            $once->as(''),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $once,
            $once->until(new DateInterval('PT1H')),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $once,
            $once->until(new DateTimeImmutable('+1 hour')),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $once,
            $once->until(0),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
    }

    public function testUntilMillisecondPrecision(): void
    {
        $target = new DateTimeImmutable('2030-06-15T12:00:00+00:00');

        $once = (new OnceProp(fn() => []))
            ->until($target);

        $expected = $target->getTimestamp() * 1000;

        self::assertSame(
            $expected,
            $once->getExpiresAtMs(),
            "Expiration must be exactly 'timestamp * 1000'.",
        );
        self::assertSame(
            0,
            $expected % 1000,
            "Millisecond value must be a multiple of '1000'.",
        );
    }

    public function testUntilWithDateInterval(): void
    {
        $before = new DateTimeImmutable();

        $once = (new OnceProp(fn() => []))
            ->until(new DateInterval('PT1H'));

        $after = new DateTimeImmutable();

        $expiresAt = $once->getExpiresAtMs();

        self::assertNotNull(
            $expiresAt,
            "Should set 'expiresAtMs' after 'until(DateInterval)'.",
        );

        $expectedMin = ($before->getTimestamp() + 3600) * 1000;
        $expectedMax = ($after->getTimestamp() + 3600) * 1000;

        self::assertGreaterThanOrEqual(
            $expectedMin,
            $expiresAt,
            "Expiration should be at least 'now + 1' hour in milliseconds.",
        );
        self::assertLessThanOrEqual(
            $expectedMax,
            $expiresAt,
            "Expiration should be at most 'now + 1' hour in milliseconds.",
        );
    }

    public function testUntilWithDateTimeInterface(): void
    {
        $target = new DateTimeImmutable('2030-01-01T00:00:00+00:00');

        $once = (new OnceProp(fn() => []))
            ->until($target);

        self::assertSame(
            $target->getTimestamp() * 1000,
            $once->getExpiresAtMs(),
            "'until(DateTimeInterface)' should set 'expiresAtMs' to 'timestamp * 1000'.",
        );
    }

    public function testUntilWithIntegerAddsDelayNotSubtracts(): void
    {
        $once = (new OnceProp(fn() => []))
            ->until(7200);

        $now = time();

        $expiresAt = $once->getExpiresAtMs();

        self::assertNotNull($expiresAt);
        self::assertGreaterThan(
            $now * 1000,
            $expiresAt,
            'Expiration should be in the future, confirming delay is added, not subtracted.',
        );
    }

    public function testUntilWithIntegerSeconds(): void
    {
        $before = time();

        $once = (new OnceProp(fn() => []))
            ->until(3600);

        $after = time();

        $expiresAt = $once->getExpiresAtMs();

        self::assertNotNull(
            $expiresAt,
            "Should set 'expiresAtMs' after 'until(int)'.",
        );

        $expectedMin = ($before + 3600) * 1000;
        $expectedMax = ($after + 3600) * 1000;

        self::assertGreaterThanOrEqual(
            $expectedMin,
            $expiresAt,
            "Expiration should be at least '(now + delay) * 1000'.",
        );
        self::assertLessThanOrEqual(
            $expectedMax,
            $expiresAt,
            "Expiration should be at most '(now + delay) * 1000'.",
        );
    }
}
