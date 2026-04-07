<?php

declare(strict_types=1);

namespace yii\inertia\tests\support\stub;

use yii\inertia\BaseVite;

/**
 * Stub of {@see BaseVite} used for direct unit testing of the abstract base class.
 *
 * Defaults preserve base behavior so tests cover every code path of `BaseVite`. The `$extraTags` property and the
 * captured `$capturedDevServerUrl` enable the same stub to verify the {@see BaseVite::renderExtraDevelopmentTags()}
 * hook contract: when `$extraTags` is empty the override delegates to the parent (covering the base implementation),
 * and when populated the override prepends them to the development output.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class Vite extends BaseVite
{
    /**
     * Captured dev server URL from the last call to {@see renderExtraDevelopmentTags()} for assertion in tests.
     */
    public string|null $capturedDevServerUrl = null;
    /**
     * @phpstan-var string[] Vite entrypoints to render.
     */
    public array $entrypoints = [
        'resources/js/app.js',
    ];

    /**
     * @phpstan-var string[] Extra HTML tags returned by {@see renderExtraDevelopmentTags()} when non-empty.
     */
    public array $extraTags = [];

    /**
     * Records the resolved dev server URL and either returns the configured extra tags or delegates to the base
     * implementation when no extras are configured.
     *
     * @param string $devServerUrl Resolved Vite dev server base URL without trailing slash.
     *
     * @return array Extra HTML tags to prepend to the development output.
     *
     * @phpstan-return string[]
     */
    protected function renderExtraDevelopmentTags(string $devServerUrl): array
    {
        $this->capturedDevServerUrl = $devServerUrl;

        if ($this->extraTags === []) {
            return parent::renderExtraDevelopmentTags($devServerUrl);
        }

        return $this->extraTags;
    }
}
