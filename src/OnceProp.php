<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Wraps a closure that is resolved once and cached on the client side.
 *
 * Once props are included in the first response and excluded from subsequent requests unless the client-side cache has
 * expired. The client sends already-cached keys via `X-Inertia-Except-Once-Props`, and the server skips resolving them.
 *
 * Usage example:
 *
 * ```php
 * return \yii\inertia\Inertia::render(
 *     'Settings', [
 *         'countries' => \yii\inertia\Inertia::once(fn () => Country::find()->all())->until(3600),
 *     ]
 * );
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class OnceProp
{
    private int|null $expiresAtMs = null;
    private string|null $key = null;

    /**
     * @param Closure $callback Closure resolved once and cached by the client.
     *
     * @phpstan-param Closure(): mixed $callback
     */
    public function __construct(private readonly Closure $callback) {}

    /**
     * Sets a custom cache key for this prop.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::once(fn () => Country::find()->all())->as('countries-list');
     * ```
     *
     * @param string $key Unique identifier for client-side caching.
     *
     * @return self New instance with the updated cache key.
     */
    public function as(string $key): self
    {
        $clone = clone $this;
        $clone->key = $key;

        return $clone;
    }

    /**
     * Returns the callback resolved once and cached by the client.
     *
     * Usage example:
     *
     * ```php
     * $once = new \yii\inertia\OnceProp(fn () => Country::find()->all());
     * $callback = $once->getCallback();
     * ```
     *
     * @return Closure Callback that produces the once-prop value.
     *
     * @phpstan-return Closure(): mixed
     */
    public function getCallback(): Closure
    {
        return $this->callback;
    }

    /**
     * Returns the expiration timestamp in milliseconds, or `null` for no expiration.
     *
     * Usage example:
     *
     * ```php
     * $once = \yii\inertia\Inertia::once(fn () => [])->until(3600);
     * $expiresAt = $once->getExpiresAtMs(); // milliseconds timestamp
     * ```
     *
     * @return int|null Expiration timestamp in milliseconds, or `null` when no TTL is set.
     */
    public function getExpiresAtMs(): int|null
    {
        return $this->expiresAtMs;
    }

    /**
     * Returns the custom cache key, or `null` when the prop path is used as the key.
     *
     * Usage example:
     *
     * ```php
     * $once = \yii\inertia\Inertia::once(fn () => [])->as('my-key');
     * $key = $once->getKey(); // 'my-key'
     * ```
     *
     * @return string|null Custom cache key, or `null` when not set.
     */
    public function getKey(): string|null
    {
        return $this->key;
    }

    /**
     * Sets the time-to-live for the client-side cache.
     *
     * Usage example:
     *
     * ```php
     * // expire after 1 hour (3600 seconds).
     * \yii\inertia\Inertia::once(fn () => Country::find()->all())->until(3600);
     *
     * // expire at an absolute date.
     * \yii\inertia\Inertia::once(fn () => [])->until(new \DateTimeImmutable('+1 day'));
     * ```
     *
     * @param DateInterval|DateTimeInterface|int $delay Seconds (int), a `DateInterval`, or an absolute
     * `DateTimeInterface`.
     *
     * @return self New instance with the updated expiration.
     */
    public function until(DateInterval|DateTimeInterface|int $delay): self
    {
        $clone = clone $this;

        if ($delay instanceof DateTimeInterface) {
            $clone->expiresAtMs = $delay->getTimestamp() * 1000;

            return $clone;
        }

        if ($delay instanceof DateInterval) {
            $now = new DateTimeImmutable();
            $clone->expiresAtMs = $now->add($delay)->getTimestamp() * 1000;

            return $clone;
        }

        $clone->expiresAtMs = (time() + $delay) * 1000;

        return $clone;
    }
}
