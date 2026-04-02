<?php

declare(strict_types=1);

namespace yii\inertia;

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
