<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;

/**
 * Wraps a closure that is only resolved when explicitly requested via a partial reload.
 *
 * Unlike {@see DeferredProp}, optional props do not appear in `deferredProps` metadata and are never automatically
 * fetched by the client. They are only included when the client lists them in the `X-Inertia-Partial-Data` header.
 *
 * Usage example:
 *
 * ```php
 * return \yii\inertia\Inertia::render(
 *     'Users/Show',
 *     [
 *         'user' => $user->toArray(),
 *         'activity' => \yii\inertia\Inertia::optional(fn () => $user->getActivityLog()),
 *     ]
 * );
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class OptionalProp
{
    /**
     * @param Closure $callback Closure resolved only during partial reloads that explicitly request this prop.
     *
     * @phpstan-param Closure(): mixed $callback
     */
    public function __construct(private readonly Closure $callback) {}

    /**
     * Returns the callback resolved only during partial reloads that explicitly request this prop.
     *
     * Usage example:
     *
     * ```php
     * $optional = new \yii\inertia\OptionalProp(fn () => $user->getActivityLog());
     * $callback = $optional->getCallback();
     * ```
     *
     * @return Closure Callback that produces the optional prop value.
     *
     * @phpstan-return Closure(): mixed
     */
    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
