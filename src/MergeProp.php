<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;

/**
 * Wraps a value that merges with existing client-side data during partial reloads instead of replacing it.
 *
 * Supports shallow merge, deep merge, append/prepend at specific paths, and deduplication via match keys.
 * Merge behavior only applies during partial reloads; full page visits always replace props entirely.
 *
 * Usage example:
 *
 * ```php
 * return \yii\inertia\Inertia::render(
 *     'Users/Index',
 *     [
 *         'users' => \yii\inertia\Inertia::merge($paginatedUsers),
 *         'logs' => \yii\inertia\Inertia::deepMerge($nestedLogs),
 *     ],
 * );
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class MergeProp
{
    /**
     * @phpstan-var array<string, string> Paths mapped to their match-on keys for append operations.
     */
    private array $appendPaths = [];
    private bool $deep = false;

    /**
     * @phpstan-var array<string, string> Paths mapped to their match-on keys for prepend operations.
     */
    private array $prependPaths = [];

    /**
     * @param Closure|mixed $value Value or closure to merge with existing client-side data.
     */
    public function __construct(private readonly mixed $value) {}

    /**
     * Configures paths where data is appended and optional deduplication keys.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::merge($users)->append('data', matchOn: 'id');
     * ```
     *
     * @param array|string $paths Path or paths where append applies, or an associative map of `path => matchKey`.
     * @param string|null $matchOn Default match key for deduplication when `$paths` is a flat list.
     *
     * @return self New instance with the updated configuration.
     *
     * @phpstan-param array<int|string, string>|string $paths
     */
    public function append(array|string $paths = [], string|null $matchOn = null): self
    {
        $clone = clone $this;
        $clone->appendPaths = $this->normalizePaths($paths, $matchOn);

        return $clone;
    }

    /**
     * Enables recursive deep-merge behavior.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::merge($config)->deepMerge();
     * ```
     *
     * @return self New instance with the updated configuration.
     */
    public function deepMerge(): self
    {
        $clone = clone $this;
        $clone->deep = true;

        return $clone;
    }

    /**
     * Returns the configured append paths mapped to their match-on keys.
     *
     * Usage example:
     *
     * ```php
     * $merge = \yii\inertia\Inertia::merge($data)->append('data', matchOn: 'id');
     * $paths = $merge->getAppendPaths(); // ['data' => 'id']
     * ```
     *
     * @return array Associative map of `path => matchKey`.
     *
     * @phpstan-return array<string, string>
     */
    public function getAppendPaths(): array
    {
        return $this->appendPaths;
    }

    /**
     * Returns the configured prepend paths mapped to their match-on keys.
     *
     * Usage example:
     *
     * ```php
     * $merge = \yii\inertia\Inertia::merge($data)->prepend('messages');
     * $paths = $merge->getPrependPaths(); // ['messages' => '']
     * ```
     *
     * @return array Associative map of `path => matchKey`.
     *
     * @phpstan-return array<string, string>
     */
    public function getPrependPaths(): array
    {
        return $this->prependPaths;
    }

    /**
     * Returns the wrapped value to be merged with existing client-side data.
     *
     * Usage example:
     *
     * ```php
     * $merge = \yii\inertia\Inertia::merge(['item1', 'item2']);
     * $value = $merge->getValue(); // ['item1', 'item2']
     * ```
     *
     * @return mixed Value or closure to be resolved by the manager.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Returns `true` if recursive deep-merge behavior is enabled.
     *
     * Usage example:
     *
     * ```php
     * $merge = \yii\inertia\Inertia::deepMerge($config);
     * $merge->isDeep(); // true
     * ```
     *
     * @return bool `true` when deep-merge is active; otherwise, `false`.
     */
    public function isDeep(): bool
    {
        return $this->deep;
    }

    /**
     * Configures paths where data is prepended and optional deduplication keys.
     *
     * Usage example:
     *
     * ```php
     * \yii\inertia\Inertia::merge($messages)->prepend('data');
     * ```
     *
     * @param array|string $paths Path or paths where prepend applies, or an associative map of `path => matchKey`.
     * @param string|null $matchOn Default match key for deduplication when `$paths` is a flat list.
     *
     * @return self New instance with the updated configuration.
     *
     * @phpstan-param array<int|string, string>|string $paths
     */
    public function prepend(array|string $paths = [], string|null $matchOn = null): self
    {
        $clone = clone $this;
        $clone->prependPaths = $this->normalizePaths($paths, $matchOn);

        return $clone;
    }

    /**
     * Normalizes input paths into an associative map of `path => matchKey` for append/prepend operations.
     *
     * @param array|string $paths Path or paths to normalize, or an associative map of `path => matchKey`.
     * @param string|null $matchOn Default match key for deduplication when `$paths` is a flat list.
     *
     * @return array Normalized associative map of `path => matchKey` for append/prepend operations.
     *
     * @phpstan-param array<int|string, string>|string $paths
     * @phpstan-return array<string, string>
     */
    private function normalizePaths(array|string $paths, string|null $matchOn): array
    {
        if ($paths === [] || $paths === '') {
            return [];
        }

        if (is_string($paths)) {
            return $matchOn !== null ? [$paths => $matchOn] : [$paths => ''];
        }

        $normalized = [];

        foreach ($paths as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = $matchOn ?? '';

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
