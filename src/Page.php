<?php

declare(strict_types=1);

namespace yii\inertia;

use JsonSerializable;

/**
 * Represents an immutable Inertia page payload serialized into every server response.
 *
 * Uses fluent `with*()` methods that return a new instance, keeping the original unchanged.
 *
 * Usage example:
 *
 * ```php
 * $page = (new \yii\inertia\Page('Dashboard', ['user' => $user->toArray()], '/dashboard', 'build-1'))
 *     ->withFlash(['success' => 'Saved.'])
 *     ->withDeferredProps(['default' => ['users']]);
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class Page implements JsonSerializable
{
    private bool $clearHistory = false;

    /**
     * @phpstan-var list<string>
     */
    private array $deepMergeProps = [];

    /**
     * @phpstan-var array<string, list<string>>
     */
    private array $deferredProps = [];
    private bool $encryptHistory = false;

    /**
     * @phpstan-var array<string, mixed>
     */
    private array $flash = [];

    /**
     * @phpstan-var array<string, string>
     */
    private array $matchPropsOn = [];

    /**
     * @phpstan-var list<string>
     */
    private array $mergeProps = [];

    /**
     * @phpstan-var array<string, array<string, mixed>>
     */
    private array $onceProps = [];

    /**
     * @phpstan-var list<string>
     */
    private array $prependProps = [];

    /**
     * @phpstan-var array<string, array<string, mixed>>
     */
    private array $scrollProps = [];

    /**
     * @param string $component Frontend component name.
     * @param array $props Props forwarded to the frontend component.
     * @param string $url Current request URL included in the page payload.
     * @param int|string $version Asset version used for client-side mismatch detection.
     *
     * @phpstan-param array<string, mixed> $props
     */
    public function __construct(
        private readonly string $component,
        private readonly array $props,
        private readonly string $url,
        private readonly int|string $version = '',
    ) {}

    /**
     * Serializes the page into an array structure matching the Inertia page schema.
     *
     * Usage example:
     *
     * ```php
     * $page = new \yii\inertia\Page('Home', ['title' => 'Welcome'], '/', 'v1');
     * $payload = $page->jsonSerialize();
     * ```
     *
     * @return array Page payload as an associative array ready for JSON serialization.
     *
     * @phpstan-return array{
     *   component: string,
     *   props: array<string, mixed>,
     *   url: string,
     *   version: int|string,
     *   flash?: array<string, mixed>,
     *   clearHistory?: bool,
     *   encryptHistory?: bool,
     *   deferredProps?: array<string, list<string>>,
     *   mergeProps?: list<string>,
     *   prependProps?: list<string>,
     *   deepMergeProps?: list<string>,
     *   matchPropsOn?: array<string, string>,
     *   scrollProps?: array<string, array<string, mixed>>,
     *   onceProps?: array<string, array<string, mixed>>,
     * }
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

        if ($this->deferredProps !== []) {
            $page['deferredProps'] = $this->deferredProps;
        }

        if ($this->mergeProps !== []) {
            $page['mergeProps'] = $this->mergeProps;
        }

        if ($this->prependProps !== []) {
            $page['prependProps'] = $this->prependProps;
        }

        if ($this->deepMergeProps !== []) {
            $page['deepMergeProps'] = $this->deepMergeProps;
        }

        if ($this->matchPropsOn !== []) {
            $page['matchPropsOn'] = $this->matchPropsOn;
        }

        if ($this->scrollProps !== []) {
            $page['scrollProps'] = $this->scrollProps;
        }

        if ($this->onceProps !== []) {
            $page['onceProps'] = $this->onceProps;
        }

        return $page;
    }

    /**
     * Returns a new instance with the clear-history flag enabled.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Login', [], '/login'))
     *     ->withClearHistory();
     * ```
     *
     * @param bool $clearHistory Whether to clear the page history, preventing back navigation to previous pages.
     *
     * @return self New instance with the updated clearHistory flag.
     */
    public function withClearHistory(bool $clearHistory = true): self
    {
        $clone = clone $this;
        $clone->clearHistory = $clearHistory;

        return $clone;
    }

    /**
     * Returns a new instance with the given deep-merge prop paths.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Settings', $props, '/settings'))
     *     ->withDeepMergeProps(['config']);
     * ```
     *
     * @param array $deepMergeProps Prop paths the client should recursively deep-merge.
     *
     * @return self New instance with the updated deepMergeProps.
     *
     * @phpstan-param list<string> $deepMergeProps
     */
    public function withDeepMergeProps(array $deepMergeProps): self
    {
        $clone = clone $this;
        $clone->deepMergeProps = $deepMergeProps;

        return $clone;
    }

    /**
     * Returns a new instance with the given deferred-props metadata.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Dashboard', $props, '/'))
     *     ->withDeferredProps(['default' => ['users', 'roles']]);
     * ```
     *
     * @param array $deferredProps Map of group name to prop keys for deferred loading.
     *
     * @return self New instance with the updated deferredProps.
     *
     * @phpstan-param array<string, list<string>> $deferredProps
     */
    public function withDeferredProps(array $deferredProps): self
    {
        $clone = clone $this;
        $clone->deferredProps = $deferredProps;

        return $clone;
    }

    /**
     * Returns a new instance with the encrypt-history flag enabled.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Profile', $props, '/profile'))
     *     ->withEncryptHistory();
     * ```
     *
     * @param bool $encryptHistory Whether to encrypt the page history for enhanced security.
     *
     * @return self New instance with the updated encryptHistory flag.
     */
    public function withEncryptHistory(bool $encryptHistory = true): self
    {
        $clone = clone $this;
        $clone->encryptHistory = $encryptHistory;

        return $clone;
    }

    /**
     * Returns a new instance with the given flash data.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Home', [], '/'))
     *     ->withFlash(['success' => 'Saved.']);
     * ```
     *
     * @param array $flash Session flash data exposed outside `props`.
     *
     * @return self New instance with the updated flash data.
     *
     * @phpstan-param array<string, mixed> $flash
     */
    public function withFlash(array $flash): self
    {
        $clone = clone $this;
        $clone->flash = $flash;

        return $clone;
    }

    /**
     * Returns a new instance with the given match-props-on metadata.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Users', $props, '/users'))
     *     ->withMatchPropsOn(['users.data' => 'id']);
     * ```
     *
     * @param array $matchPropsOn Map of prop path to match key for deduplication during merge.
     *
     * @return self New instance with the updated matchPropsOn.
     *
     * @phpstan-param array<string, string> $matchPropsOn
     */
    public function withMatchPropsOn(array $matchPropsOn): self
    {
        $clone = clone $this;
        $clone->matchPropsOn = $matchPropsOn;

        return $clone;
    }

    /**
     * Returns a new instance with the given merge prop paths.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Users', $props, '/users'))
     *     ->withMergeProps(['users']);
     * ```
     *
     * @param array $mergeProps Prop paths the client should shallow-merge instead of replacing.
     *
     * @return self New instance with the updated mergeProps.
     *
     * @phpstan-param list<string> $mergeProps
     */
    public function withMergeProps(array $mergeProps): self
    {
        $clone = clone $this;
        $clone->mergeProps = $mergeProps;

        return $clone;
    }

    /**
     * Returns a new instance with the given once-props metadata.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Settings', $props, '/settings'))
     *     ->withOnceProps(['countries' => ['prop' => 'countries', 'expiresAt' => 1700000000000]]);
     * ```
     *
     * @param array $onceProps Once-prop metadata with optional expiration timestamps.
     *
     * @return self New instance with the updated onceProps.
     *
     * @phpstan-param array<string, array<string, mixed>> $onceProps
     */
    public function withOnceProps(array $onceProps): self
    {
        $clone = clone $this;
        $clone->onceProps = $onceProps;

        return $clone;
    }

    /**
     * Returns a new instance with the given prepend prop paths.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Chat', $props, '/chat'))
     *     ->withPrependProps(['messages']);
     * ```
     *
     * @param array $prependProps Prop paths the client should prepend instead of appending.
     *
     * @return self New instance with the updated prependProps.
     *
     * @phpstan-param list<string> $prependProps
     */
    public function withPrependProps(array $prependProps): self
    {
        $clone = clone $this;
        $clone->prependProps = $prependProps;

        return $clone;
    }

    /**
     * Returns a new instance with the given scroll-props metadata.
     *
     * Usage example:
     *
     * ```php
     * $page = (new \yii\inertia\Page('Feed', $props, '/feed'))
     *     ->withScrollProps(['posts' => ['pageName' => 'page', 'currentPage' => 1, 'nextPage' => 2]]);
     * ```
     *
     * @param array $scrollProps Infinite scroll pagination metadata per prop.
     *
     * @return self New instance with the updated scrollProps.
     *
     * @phpstan-param array<string, array<string, mixed>> $scrollProps
     */
    public function withScrollProps(array $scrollProps): self
    {
        $clone = clone $this;
        $clone->scrollProps = $scrollProps;

        return $clone;
    }
}
