<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Provides a static helper over the Inertia application component.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class Inertia
{
    private const COMPONENT_ID = 'inertia';

    /**
     * Creates a prop that is always included in every response, bypassing partial-reload filtering.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::render(
     *     'Dashboard',
     *     [
     *         'auth' => \yii\inertia\Inertia::always(fn () => ['user' => Yii::$app->user->identity]),
     *     ],
     * );
     * ```
     *
     * @param Closure|mixed $value Value or closure always included in responses.
     *
     * @return AlwaysProp Prop instance that is always included in responses.
     */
    public static function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Creates a prop that deep-merges with existing client-side data during partial reloads.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::render(
     *     'Settings',
     *     [
     *         'config' => \yii\inertia\Inertia::deepMerge($nestedConfig),
     *     ]
     * );
     * ```
     *
     * @param Closure|mixed $value Value or closure to deep-merge.
     *
     * @return MergeProp Prop instance that deep-merges with existing client-side data during partial reloads.
     */
    public static function deepMerge(mixed $value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * Creates a deferred prop whose evaluation is postponed until the client requests it via a partial reload.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::render(
     *     'Dashboard',
     *     [
     *         'users' => \yii\inertia\Inertia::defer(fn () => User::find()->all()),
     *         'roles' => \yii\inertia\Inertia::defer(fn () => Role::find()->all(), 'attributes'),
     *     ]
     * );
     * ```
     *
     * @param Closure $callback Closure resolved when the client requests this prop.
     * @param string $group Group name for batching deferred requests.
     *
     * @return DeferredProp Prop instance that is resolved when the client requests it via a partial reload.
     *
     * @phpstan-param (Closure(): mixed)|(Closure(\yii\web\Request): mixed) $callback
     */
    public static function defer(Closure $callback, string $group = 'default'): DeferredProp
    {
        return new DeferredProp($callback, $group);
    }

    /**
     * Removes all shared props registered for the current request.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::flushShared();
     * ```
     */
    public static function flushShared(): void
    {
        self::manager()->flushShared();
    }

    /**
     * Returns the shared props or the nested value at `$key`.
     *
     * Usage example:
     *
     * ```php
     * // return all shared props.
     * $all = \yii\inertia\Inertia::getShared();
     *
     * // return a nested value using dot notation.
     * $name = \yii\inertia\Inertia::getShared('auth.user.name', 'Guest');
     * ```
     *
     * @param string|null $key Dot-notation key to retrieve, or `null` to return all shared props.
     * @param mixed $default Value returned when `$key` is not found.
     *
     * @return mixed Shared value at `$key`, or `$default` when the key does not exist.
     */
    public static function getShared(string|null $key = null, mixed $default = null): mixed
    {
        return self::manager()->getShared($key, $default);
    }

    /**
     * Returns the resolved asset version.
     *
     * Usage example:
     *
     * ```php
     * $version = \yii\inertia\Inertia::getVersion();
     * ```
     *
     * @return int|string Resolved version, or an empty `string` when none is configured.
     */
    public static function getVersion(): int|string
    {
        return self::manager()->getVersion();
    }

    /**
     * Returns a `409` Inertia location response for Inertia requests, or a standard `302` redirect otherwise.
     *
     * Usage example:
     *
     * ```php
     * return \yii\inertia\Inertia::location('/login');
     * ```
     *
     * @param array|string $url Destination URL or route array accepted by `Url::to()`.
     *
     * @return Response Response instance with the appropriate status code and headers for the request type.
     *
     * @phpstan-param array<string, mixed>|string $url
     */
    public static function location(array|string $url): Response
    {
        return self::manager()->location($url);
    }

    /**
     * Creates a prop that merges with existing client-side data during partial reloads instead of replacing it.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::render(
     *     'Users/Index', [
     *         'users' => \yii\inertia\Inertia::merge($paginatedUsers)->append('data', matchOn: 'id'),
     *     ]
     * );
     * ```
     *
     * @param Closure|mixed $value Value or closure to merge.
     *
     * @return MergeProp Prop instance that merges with existing client-side data during partial reloads instead of
     * replacing it.
     */
    public static function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Creates a prop that is resolved once and cached on the client side.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::render(
     *     'Settings', [
     *         'countries' => \yii\inertia\Inertia::once(fn () => Country::find()->all())->until(3600),
     *     ]
     * );
     * ```
     *
     * @param Closure $callback Closure resolved once and cached by the client.
     *
     * @return OnceProp Prop instance that is resolved once and cached on the client side.
     *
     * @phpstan-param (Closure(): mixed)|(Closure(\yii\web\Request): mixed) $callback
     */
    public static function once(Closure $callback): OnceProp
    {
        return new OnceProp($callback);
    }

    /**
     * Creates a prop that is only resolved when explicitly requested via a partial reload.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::render(
     *     'Users/Show', [
     *         'user' => $user->toArray(),
     *         'activity' => \yii\inertia\Inertia::optional(fn () => $user->getActivityLog()),
     *     ]
     * );
     * ```
     *
     * @param Closure $callback Closure resolved only during partial reloads that explicitly request this prop.
     *
     * @return OptionalProp Prop instance that is only resolved when explicitly requested via a partial reload.
     *
     * @phpstan-param (Closure(): mixed)|(Closure(\yii\web\Request): mixed) $callback
     */
    public static function optional(Closure $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Renders an Inertia page response.
     *
     * Returns a JSON page payload with `X-Inertia: true` for Inertia requests, or the initial HTML
     * shell for standard browser requests.
     *
     * Usage example:
     *
     * ```php
     * return \yii\inertia\Inertia::render(
     *     'Dashboard',
     *     [
     *         'user' => $user->toArray(),
     *     ],
     * );
     * ```
     *
     * @param string $component Frontend component name (for example, `'Dashboard'`, `'User/Show'`).
     * @param array $props Props serialized and forwarded to the frontend component.
     * @param array $viewData Additional data available in the root view template only; not sent to the frontend.
     *
     * @return Response Response instance with the appropriate content and headers for the request type.
     *
     * @phpstan-param array<string, mixed> $props
     * @phpstan-param array<string, mixed> $viewData
     */
    public static function render(string $component, array $props = [], array $viewData = []): Response
    {
        return self::manager()->render($component, $props, $viewData);
    }

    /**
     * Registers props shared with every subsequent Inertia response in the current request.
     *
     * Usage example:
     *
     * ```php
     * // share a single value using dot notation.
     * \yii\inertia\Inertia::share('auth.user', fn() => \Yii::$app->user->identity);
     *
     * // share multiple values at once.
     * \yii\inertia\Inertia::share(['locale' => 'en', 'flash' => []]);
     * ```
     *
     * @param array|string $key Dot-notation key or an array of key-value pairs to share.
     * @param mixed $value Value to share; ignored when `$key` is an array.
     *
     * @phpstan-param array<string, mixed>|string $key
     */
    public static function share(array|string $key, mixed $value = null): void
    {
        self::manager()->share($key, $value);
    }

    /**
     * Returns the Inertia application component instance.
     *
     * @throws InvalidConfigException if the component is not properly configured or does not extend `Manager`.
     *
     * @return Manager Inertia application component instance.
     */
    private static function manager(): Manager
    {
        $manager = Yii::$app->get(self::COMPONENT_ID);

        if (!$manager instanceof Manager) {
            throw new InvalidConfigException(
                'The "inertia" application component must be an instance of ' . Manager::class . '.',
            );
        }

        return $manager;
    }
}
