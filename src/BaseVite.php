<?php

declare(strict_types=1);

namespace yii\inertia;

use Throwable;
use Yii;
use yii\base\{Component, InvalidConfigException};
use yii\helpers\{Html, Json};

use function is_array;
use function is_string;
use function sprintf;

/**
 * Base Vite entrypoint renderer for Inertia adapters.
 *
 * Provides a development mode that points directly to the Vite dev server, and a production mode that reads Vite's
 * manifest file and renders stylesheets, module entry scripts, and optional modulepreload tags.
 *
 * Subclasses may override {@see renderExtraDevelopmentTags()} to inject framework-specific bootstrap tags (for
 * example, the React Refresh preamble used by `@vitejs/plugin-react` on traditional backends) ahead of the
 * `@vite/client` script in development mode.
 *
 * Usage example:
 *
 * ```php
 * // config/web.php
 * return [
 *     'components' => [
 *         'inertiaVite' => [
 *             'class' => \yii\inertia\vue\Vite::class,
 *             'manifestPath' => '@webroot/build/.vite/manifest.json',
 *             'baseUrl' => '@web/build',
 *             'entrypoints' => ['resources/js/app.js'],
 *             'devMode' => YII_ENV_DEV,
 *             'devServerUrl' => 'http://localhost:5173',
 *         ],
 *     ],
 * ];
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
abstract class BaseVite extends Component
{
    /**
     * Base URL prefix for built assets referenced by the Vite manifest.
     */
    public string $baseUrl = '@web/build';
    /**
     * Whether the Vite dev server should be used instead of the build manifest.
     */
    public bool $devMode = false;
    /**
     * Vite development server URL.
     */
    public string|null $devServerUrl = null;
    /**
     * @phpstan-var string[] Vite entrypoints to render.
     */
    public array $entrypoints = [];
    /**
     * Whether to include the `@vite/client` development script when in development mode.
     */
    public bool $includeViteClient = true;
    /**
     * Path to the Vite manifest file.
     */
    public string $manifestPath = '@webroot/build/.vite/manifest.json';
    /**
     * Whether to render `modulepreload` tags for imported JavaScript chunks in production mode.
     */
    public bool $modulePreload = true;

    /**
     * @phpstan-var array<string, array<string, mixed>>|null Cached Vite manifest contents.
     */
    private array|null $manifest = null;

    /**
     * Renders the HTML tags for the configured or provided entrypoints.
     *
     * Returns CSS stylesheet tags, module script tags, and optional modulepreload tags in production mode, or dev
     * server script tags in development mode.
     *
     * Usage example:
     *
     * ```php
     * $vite = Yii::$app->get('inertiaVite');
     *
     * // render tags for the default configured entrypoints.
     * echo $vite->renderTags();
     *
     * // render tags for a specific entrypoint.
     * echo $vite->renderTags('resources/js/admin.js');
     * ```
     *
     * @param array|string|null $entrypoints Entrypoints to render, or `null` to use the configured defaults.
     *
     * @throws InvalidConfigException if the manifest is missing, invalid, or entrypoints cannot be resolved.
     *
     * @return string Concatenated HTML tags ready for output.
     *
     * @phpstan-param array<mixed>|string|null $entrypoints
     */
    public function renderTags(array|string|null $entrypoints = null): string
    {
        $entrypoints = $this->normalizeEntrypoints($entrypoints ?? $this->entrypoints);

        return $this->devMode
            ? $this->renderDevelopmentTags($entrypoints)
            : $this->renderBuildTags($entrypoints);
    }

    /**
     * Returns extra development-mode tags emitted before the `@vite/client` script tag.
     *
     * Override in subclasses to inject framework-specific bootstrap tags (for example, the React Refresh preamble).
     * The default implementation returns an empty list.
     *
     * @param string $devServerUrl Resolved Vite dev server base URL without trailing slash.
     *
     * @return array Extra HTML tags to prepend to the development output.
     *
     * @phpstan-return string[]
     */
    protected function renderExtraDevelopmentTags(string $devServerUrl): array
    {
        return [];
    }

    /**
     * Pushes every CSS file declared by a manifest chunk into the accumulator.
     *
     * @param array $cssTags Accumulated CSS tags keyed by source file path; modified by reference.
     * @param array $chunk Chunk descriptor whose `css` key will be traversed.
     *
     * @phpstan-param array<string, string> $cssTags
     * @phpstan-param array<string, mixed> $chunk
     */
    private function collectCssFiles(array &$cssTags, array $chunk): void
    {
        $cssFiles = $chunk['css'] ?? [];

        if (!is_array($cssFiles)) {
            return;
        }

        foreach ($cssFiles as $cssFile) {
            $this->pushCssTag($cssTags, $cssFile);
        }
    }

    /**
     * Recursively collects all transitive import chunks for a given manifest chunk.
     *
     * Stores each visited chunk under its import key in `$collected`, which doubles as the dedup set and the
     * accumulator. Adding to `$collected` BEFORE recursing breaks circular dependency cycles, since any chunk that
     * appears in its own import graph will already be present when the recursion revisits it.
     *
     * @param array $manifest Parsed Vite manifest.
     * @param array $chunk Chunk descriptor whose `imports` key will be traversed.
     * @param array $collected Map of already-visited import keys to their chunk descriptors; passed by reference to
     * accumulate across recursive calls.
     *
     * @return array Map of import key to chunk descriptor in pre-order (parent before its imports).
     *
     * @phpstan-param array<string, array<string, mixed>> $manifest
     * @phpstan-param array<string, mixed> $chunk
     * @phpstan-param array<string, array<string, mixed>> $collected
     * @phpstan-return array<string, array<string, mixed>>
     */
    private function getImportedChunks(array $manifest, array $chunk, array &$collected = []): array
    {
        $imports = $chunk['imports'] ?? [];

        if (is_array($imports)) {
            foreach ($imports as $import) {
                if (!is_string($import) || isset($collected[$import])) {
                    continue;
                }

                $importedChunk = $this->getManifestChunk($manifest, $import);

                $collected[$import] = $importedChunk;

                $this->getImportedChunks($manifest, $importedChunk, $collected);
            }
        }

        return $collected;
    }

    /**
     * Reads, decodes, validates, and caches the Vite manifest file.
     *
     * @throws InvalidConfigException if the manifest file is missing, unreadable, or contains invalid data.
     *
     * @return array Parsed manifest keyed by source entrypoint path.
     *
     * @phpstan-return array<string, array<string, mixed>>
     */
    private function getManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = Yii::getAlias($this->manifestPath);

        if (!is_file($path)) {
            throw new InvalidConfigException(
                sprintf('The Vite manifest file "%s" does not exist.', $path),
            );
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new InvalidConfigException(
                sprintf('Unable to read the Vite manifest file "%s".', $path),
            );
        }

        try {
            /** @phpstan-var array<string, array<string, mixed>>|null $manifest */
            $manifest = Json::decode($content);
        } catch (Throwable $e) {
            throw new InvalidConfigException(
                sprintf('Unable to decode the Vite manifest file "%s": %s', $path, $e->getMessage()),
                previous: $e,
            );
        }

        if (!is_array($manifest)) {
            throw new InvalidConfigException(
                sprintf('The Vite manifest file "%s" must decode to an array.', $path),
            );
        }

        return $this->manifest = $manifest;
    }

    /**
     * Returns the validated chunk descriptor for a single entrypoint from the manifest.
     *
     * @param array $manifest Parsed Vite manifest.
     * @param string $entrypoint Source entrypoint path (e.g. `resources/js/app.js`).
     *
     * @throws InvalidConfigException if the entrypoint is missing or its chunk is malformed.
     *
     * @return array Chunk descriptor containing at least a `file` key.
     *
     * @phpstan-param array<string, array<string, mixed>> $manifest
     * @phpstan-return array<string, mixed>
     */
    private function getManifestChunk(array $manifest, string $entrypoint): array
    {
        if (!isset($manifest[$entrypoint])) {
            throw new InvalidConfigException(
                sprintf('The Vite manifest does not contain the entrypoint "%s".', $entrypoint),
            );
        }

        $chunk = $manifest[$entrypoint];

        if (!isset($chunk['file']) || !is_string($chunk['file'])) {
            throw new InvalidConfigException(
                sprintf('The Vite manifest entry "%s" is invalid.', $entrypoint),
            );
        }

        return $chunk;
    }

    /**
     * Validates and normalizes entrypoints into a non-empty list of trimmed strings.
     *
     * Downstream rendering deduplicates by output file path, so this method does not need to remove duplicates from the
     * source entrypoint list.
     *
     * @param array|string $entrypoints Raw entrypoints to normalize.
     *
     * @throws InvalidConfigException if the resulting list is empty or contains non-string values.
     *
     * @return array Non-empty list of trimmed entrypoint paths.
     *
     * @phpstan-param array<mixed>|string $entrypoints
     * @phpstan-return string[]
     */
    private function normalizeEntrypoints(array|string $entrypoints): array
    {
        $entrypoints = is_array($entrypoints) ? $entrypoints : [$entrypoints];
        $normalized = [];

        foreach ($entrypoints as $entrypoint) {
            if (!is_string($entrypoint)) {
                throw new InvalidConfigException(
                    'Each Vite entrypoint must be a string.',
                );
            }

            $trimmed = trim($entrypoint);

            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        if ($normalized === []) {
            throw new InvalidConfigException(
                'At least one Vite entrypoint must be configured.',
            );
        }

        return $normalized;
    }

    /**
     * Stores a CSS stylesheet tag for the given file path under its own key.
     *
     * Reassigning the same source path overwrites the previous identical tag, which transparently deduplicates the
     * accumulator without an explicit guard.
     *
     * @param array $cssTags Accumulated CSS tags keyed by source file path; modified by reference.
     * @param mixed $cssFile CSS file path from the manifest, or a non-string value to skip.
     *
     * @phpstan-param array<string, string> $cssTags
     */
    private function pushCssTag(array &$cssTags, mixed $cssFile): void
    {
        if (!is_string($cssFile)) {
            return;
        }

        $cssTags[$cssFile] = Html::cssFile($this->resolveAssetUrl($cssFile));
    }

    /**
     * Stores a module script tag (or routes a `.css` file to {@see pushCssTag()}) for the given output file path.
     *
     * Reassigning the same output path overwrites the previous identical tag, which transparently deduplicates the
     * accumulator without an explicit guard.
     *
     * @param array $scriptTags Accumulated script tags keyed by output file path; modified by reference.
     * @param array $cssTags Accumulated CSS tags keyed by source file path; modified by reference.
     * @param string $file Output file path from the manifest chunk.
     *
     * @phpstan-param array<string, string> $scriptTags
     * @phpstan-param array<string, string> $cssTags
     */
    private function pushEntrypointTag(array &$scriptTags, array &$cssTags, string $file): void
    {
        if (str_ends_with($file, '.css')) {
            $this->pushCssTag($cssTags, $file);

            return;
        }

        $scriptTags[$file] = Html::jsFile($this->resolveAssetUrl($file), ['type' => 'module']);
    }

    /**
     * Renders production-mode HTML tags from the Vite manifest for the given entrypoints.
     *
     * Emits CSS stylesheet tags, module script tags, and optional modulepreload tags for imported chunks.
     *
     * @param array $entrypoints Normalized list of entrypoint paths.
     *
     * @return string Concatenated HTML tags.
     *
     * @phpstan-param string[] $entrypoints
     */
    private function renderBuildTags(array $entrypoints): string
    {
        $manifest = $this->getManifest();

        $cssTags = [];
        $scriptTags = [];
        $preloadTags = [];

        foreach ($entrypoints as $entrypoint) {
            $entryChunk = $this->getManifestChunk($manifest, $entrypoint);
            $importedChunks = $this->getImportedChunks($manifest, $entryChunk);

            $this->collectCssFiles($cssTags, $entryChunk);

            foreach ($importedChunks as $importedChunk) {
                $this->collectCssFiles($cssTags, $importedChunk);
            }

            /** @phpstan-var string $file */
            $file = $entryChunk['file'] ?? '';

            $this->pushEntrypointTag($scriptTags, $cssTags, $file);

            if ($this->modulePreload) {
                foreach ($importedChunks as $importedChunk) {
                    /** @phpstan-var string $preloadFile */
                    $preloadFile = $importedChunk['file'] ?? '';

                    if (!str_ends_with($preloadFile, '.css')) {
                        $preloadTags[$preloadFile] = Html::tag(
                            'link',
                            '',
                            [
                                'rel' => 'modulepreload',
                                'href' => $this->resolveAssetUrl($preloadFile),
                            ],
                        );
                    }
                }
            }
        }

        $output = [];

        foreach ($cssTags as $tag) {
            $output[] = $tag;
        }

        foreach ($scriptTags as $tag) {
            $output[] = $tag;
        }

        foreach ($preloadTags as $tag) {
            $output[] = $tag;
        }

        return implode("\n", $output);
    }

    /**
     * Renders development-mode script tags pointing to the Vite dev server.
     *
     * Emits any subclass-provided extra tags via {@see renderExtraDevelopmentTags()}, then the `@vite/client` script
     * (when enabled), then one module script per entrypoint.
     *
     * @param array $entrypoints Normalized list of entrypoint paths.
     *
     * @return string Concatenated HTML script tags.
     *
     * @phpstan-param string[] $entrypoints
     */
    private function renderDevelopmentTags(array $entrypoints): string
    {
        $devServerUrl = $this->resolveDevServerUrl();
        $tags = $this->renderExtraDevelopmentTags($devServerUrl);

        if ($this->includeViteClient) {
            $tags[] = Html::jsFile($devServerUrl . '/@vite/client', ['type' => 'module']);
        }

        foreach ($entrypoints as $entrypoint) {
            $tags[] = Html::jsFile($devServerUrl . '/' . ltrim($entrypoint, '/'), ['type' => 'module']);
        }

        return implode("\n", $tags);
    }

    /**
     * Prepends the configured base URL to a manifest asset path.
     *
     * @param string $path Relative asset path from the manifest (for example, `assets/app-abc123.js`).
     *
     * @return string Absolute URL suitable for use in HTML tags.
     */
    private function resolveAssetUrl(string $path): string
    {
        $baseUrl = rtrim(Yii::getAlias($this->baseUrl), '/');

        return "{$baseUrl}/" . ltrim($path, '/');
    }

    /**
     * Validates and returns the trimmed Vite dev server URL.
     *
     * @throws InvalidConfigException if `devServerUrl` is empty or not configured.
     *
     * @return string Dev server base URL without trailing slash.
     */
    private function resolveDevServerUrl(): string
    {
        $devServerUrl = trim((string) $this->devServerUrl);

        if ($devServerUrl === '') {
            throw new InvalidConfigException(
                'The "devServerUrl" property must be configured when Vite development mode is enabled.',
            );
        }

        return rtrim($devServerUrl, '/');
    }
}
