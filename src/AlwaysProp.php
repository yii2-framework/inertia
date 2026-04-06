<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;

/**
 * Wraps a value that is always included in every response, even during partial reloads.
 *
 * Standard props are excluded during partial reloads unless explicitly listed in `X-Inertia-Partial-Data`.
 * An `AlwaysProp` bypasses this filtering entirely, ensuring the value is present in every response.
 *
 * Usage example:
 *
 * ```php
 * return \yii\inertia\Inertia::render(
 *     'Dashboard',
 *     [
 *         'auth' => \yii\inertia\Inertia::always(fn () => ['user' => Yii::$app->user->identity]),
 *         'stats' => $dashboardStats,
 *     ]
 * );
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class AlwaysProp
{
    /**
     * @param Closure|mixed $value Value or closure always included in responses.
     */
    public function __construct(private readonly mixed $value) {}

    /**
     * Returns the wrapped value to be included in the response.
     *
     * Usage example:
     *
     * ```php
     * $always = new \yii\inertia\AlwaysProp(['user' => 'admin']);
     *
     * // ['user' => 'admin']
     * $value = $always->getValue();
     * ```
     *
     * @return mixed Value or closure to be resolved by the manager.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
