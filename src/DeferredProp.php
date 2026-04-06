<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;

/**
 * Wraps a closure whose evaluation is deferred until the client explicitly requests it via a partial reload.
 *
 * Deferred props are excluded from the initial page response and loaded asynchronously after the page renders.
 * Props sharing the same group are fetched together in a single request.
 *
 * Usage example:
 *
 * ```php
 * return \yii\inertia\Inertia::render('Dashboard',
 *     [
 *         'users' => \yii\inertia\Inertia::defer(fn () => User::find()->all()),
 *         'permissions' => \yii\inertia\Inertia::defer(fn () => Permission::find()->all(), 'attributes'),
 *     ]
 * );
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class DeferredProp
{
    /**
     * @param Closure $callback Closure resolved when the client requests this prop.
     * @param string $group Group name for batching deferred requests.
     *
     * @phpstan-param (Closure(): mixed)|(Closure(\yii\web\Request): mixed) $callback
     */
    public function __construct(private readonly Closure $callback, private readonly string $group = 'default') {}

    /**
     * Returns the deferred callback to be evaluated when the client requests this prop.
     *
     * Usage example:
     *
     * ```php
     * $deferred = new \yii\inertia\DeferredProp(fn () => User::find()->all());
     * $callback = $deferred->getCallback();
     * ```
     *
     * @return Closure Closure to be evaluated when the client requests this prop.
     *
     * @phpstan-return (Closure(): mixed)|(Closure(\yii\web\Request): mixed)
     */
    public function getCallback(): Closure
    {
        return $this->callback;
    }

    /**
     * Returns the group name used to batch this deferred prop with others in a single partial-reload request.
     *
     * Usage example:
     *
     * ```php
     * $deferred = new \yii\inertia\DeferredProp(fn () => [], 'sidebar');
     *
     * // 'sidebar'
     * $group = $deferred->getGroup();
     * ```
     *
     * @return string Group identifier.
     */
    public function getGroup(): string
    {
        return $this->group;
    }
}
