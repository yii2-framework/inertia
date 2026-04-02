<?php

declare(strict_types=1);

namespace yii\inertia;

use JsonSerializable;

/**
 * Represents an immutable Inertia page payload serialized into every server response.
 *
 * Usage example:
 *
 * ```php
 * $page = new \yii\inertia\Page(
 *     component: 'Dashboard',
 *     props: [
 *         'user' => $user->toArray(),
 *     ],
 *     url: '/dashboard',
 *     version: 'build-1',
 * );
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class Page implements JsonSerializable
{
    /**
     * @param string $component Frontend component name.
     * @param array $props Props forwarded to the frontend component.
     * @param string $url Current request URL included in the page payload.
     * @param int|string $version Asset version used for client-side mismatch detection.
     * @param array $flash Session flash data exposed outside `props`.
     * @param bool $clearHistory Whether to instruct the client to clear its navigation history.
     * @param bool $encryptHistory Whether to instruct the client to encrypt its navigation history.
     *
     * @phpstan-param array<string, mixed> $props
     * @phpstan-param array<string, mixed> $flash
     */
    public function __construct(
        private readonly string $component,
        private readonly array $props,
        private readonly string $url,
        private readonly int|string $version = '',
        private readonly array $flash = [],
        private readonly bool $clearHistory = false,
        private readonly bool $encryptHistory = false,
    ) {}

    /**
     * Serializes the page into an array structure matching the Inertia page schema.
     *
     * @return array Page payload as an associative array ready for JSON serialization.
     *
     * @phpstan-return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $page = [
            'component' => $this->component,
            'props' => $this->props,
            'url' => $this->url,
            'version' => $this->version,
        ];

        if ($this->flash !== []) {
            $page['flash'] = $this->flash;
        }

        if ($this->clearHistory) {
            $page['clearHistory'] = true;
        }

        if ($this->encryptHistory) {
            $page['encryptHistory'] = true;
        }

        return $page;
    }
}
