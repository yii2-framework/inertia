<?php

declare(strict_types=1);

namespace yii\inertia\tests\support\stub;

/**
 * Stateful stub for internal PHP functions used by tests.
 *
 * Provides deterministic replacements for `trim` and `unserialize` so mutation tests can verify exact arguments.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.2
 */
final class MockerFunctions
{
    private static bool $trimCalled = false;

    /**
     * @phpstan-var list<array{data: string, options: array{allowed_classes?: array<string>|bool}}>
     */
    private static array $unserializeCalls = [];

    public static function getTrimCalled(): bool
    {
        return self::$trimCalled;
    }

    /**
     * @phpstan-return list<array{data: string, options: array{allowed_classes?: array<string>|bool}}>
     */
    public static function getUnserializeCalls(): array
    {
        return self::$unserializeCalls;
    }

    public static function reset(): void
    {
        self::$trimCalled = false;
        self::$unserializeCalls = [];
    }

    public static function trim(string $string, string $characters = " \n\r\t\v\0"): string
    {
        self::$trimCalled = true;

        return \trim($string, $characters);
    }

    /**
     * @param array{allowed_classes?: array<string>|bool} $options
     */
    public static function unserialize(string $data, array $options = []): mixed
    {
        self::$unserializeCalls[] = ['data' => $data, 'options' => $options];

        return @\unserialize($data, $options);
    }
}
