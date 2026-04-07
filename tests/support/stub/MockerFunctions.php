<?php

declare(strict_types=1);

namespace yii\inertia\tests\support\stub;

/**
 * Stateful stub for internal PHP functions hijacked during test execution.
 *
 * Provides minimal observability and failure injection for `file_get_contents`, `trim`, and `unserialize`:
 *
 * - `file_get_contents`: counts invocations and can be forced to return `false` to simulate an unreadable file.
 *   Successful reads still delegate to PHP's native function and the filename/optional arguments are NOT captured.
 * - `trim`: records whether it has been invoked at least once and delegates to the native function.
 * - `unserialize`: records every invocation with its `$data` and `$options` for exact-argument verification, then
 *   delegates to the native function with notices suppressed.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class MockerFunctions
{
    /**
     * Number of times {@see file_get_contents()} has been invoked since the last {@see reset()}.
     */
    private static int $fileGetContentsCalls = 0;
    /**
     * Whether the next {@see file_get_contents()} invocation must return `false` to simulate an unreadable file.
     */
    private static bool $fileGetContentsShouldFail = false;
    /**
     * Whether {@see trim()} has been invoked at least once since the last {@see reset()}.
     */
    private static bool $trimCalled = false;
    /**
     * Recorded {@see unserialize()} invocations with their original arguments.
     *
     * @phpstan-var list<array{data: string, options: array{allowed_classes?: array<string>|bool}}>
     */
    private static array $unserializeCalls = [];

    /**
     * Records the call, then either short-circuits with `false` or delegates to PHP's native `file_get_contents`.
     *
     * @param string $filename Filesystem path forwarded to the native function.
     * @param mixed ...$args Optional positional arguments forwarded verbatim to the native function.
     *
     * @return false|string File contents on success, or `false` when failure is simulated or the underlying call
     * fails.
     */
    public static function file_get_contents(string $filename, mixed ...$args): false|string
    {
        self::$fileGetContentsCalls++;

        if (self::$fileGetContentsShouldFail) {
            self::$fileGetContentsShouldFail = false;

            return false;
        }

        return \file_get_contents($filename, ...$args); // @phpstan-ignore argument.type
    }

    /**
     * Returns the number of {@see file_get_contents()} invocations recorded since the last {@see reset()}.
     */
    public static function getFileGetContentsCalls(): int
    {
        return self::$fileGetContentsCalls;
    }

    /**
     * Returns whether {@see trim()} has been invoked at least once since the last {@see reset()}.
     */
    public static function getTrimCalled(): bool
    {
        return self::$trimCalled;
    }

    /**
     * Returns the {@see unserialize()} invocations recorded since the last {@see reset()}.
     *
     * @phpstan-return list<array{data: string, options: array{allowed_classes?: array<string>|bool}}>
     */
    public static function getUnserializeCalls(): array
    {
        return self::$unserializeCalls;
    }

    /**
     * Resets every recorded counter, flag, and invocation log to its pristine state.
     *
     * Tests must call this in their `setUp()` to guarantee isolation across test cases.
     */
    public static function reset(): void
    {
        self::$fileGetContentsCalls = 0;
        self::$fileGetContentsShouldFail = false;
        self::$trimCalled = false;
        self::$unserializeCalls = [];
    }

    /**
     * Toggles whether the next {@see file_get_contents()} invocation must return `false` to simulate an unreadable
     * file.
     *
     * @param bool $shouldFail `true` to simulate failure, or `false` to restore normal behavior.
     */
    public static function setFileGetContentsShouldFail(bool $shouldFail = true): void
    {
        self::$fileGetContentsShouldFail = $shouldFail;
    }

    /**
     * Marks `trim` as called and delegates to PHP's native `trim` with the same arguments.
     *
     * @param string $string Subject string forwarded to the native function.
     * @param string $characters Characters to strip, forwarded to the native function.
     */
    public static function trim(string $string, string $characters = " \n\r\t\v\0"): string
    {
        self::$trimCalled = true;

        return \trim($string, $characters);
    }

    /**
     * Records the invocation, then delegates to PHP's native `unserialize` while suppressing notices.
     *
     * @param string $data Serialized payload forwarded to the native function.
     * @param array $options Options forwarded to the native function.
     *
     * @return mixed Decoded value, or `false` when the payload cannot be unserialized.
     *
     * @phpstan-param array{allowed_classes?: array<string>|bool} $options
     */
    public static function unserialize(string $data, array $options = []): mixed
    {
        self::$unserializeCalls[] = ['data' => $data, 'options' => $options];

        return @\unserialize($data, $options);
    }
}
