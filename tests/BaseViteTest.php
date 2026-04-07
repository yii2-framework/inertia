<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use yii\base\InvalidConfigException;
use yii\inertia\tests\support\stub\{MockerFunctions, Vite};

/**
 * Unit tests for {@see \yii\inertia\BaseVite}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class BaseViteTest extends TestCase
{
    public function testManifestIsCachedAfterFirstReadAndFileGetContentsRunsOnce(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $first = $vite->renderTags();

        $callsAfterFirst = MockerFunctions::getFileGetContentsCalls();

        $second = $vite->renderTags();

        $callsAfterSecond = MockerFunctions::getFileGetContentsCalls();

        self::assertSame(
            $first,
            $second,
            'Manifest should be cached after the first read and produce identical output.',
        );
        self::assertSame(
            1,
            $callsAfterFirst,
            'First render tags call must read the manifest file from disk exactly once.',
        );
        self::assertSame(
            1,
            $callsAfterSecond,
            'Second render tags call must reuse the cached manifest and never re-read the file.',
        );
    }

    public function testRenderExtraDevelopmentTagsHookReceivesResolvedDevServerUrlAndIsPrependedToOutput(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => 'http://localhost:5173/',
                'extraTags' => [
                    '<script type="module">/* extra preamble */</script>',
                ],
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            'http://localhost:5173',
            $vite->capturedDevServerUrl,
            'Extra tags hook should receive the trimmed dev server URL.',
        );
        self::assertSame(
            implode(
                "\n",
                [
                    '<script type="module">/* extra preamble */</script>',
                    '<script type="module" src="http://localhost:5173/@vite/client"></script>',
                    '<script type="module" src="http://localhost:5173/resources/js/app.js"></script>',
                ],
            ),
            $tags,
            "Extra tags should be emitted before the '@vite/client' tag and the 'entrypoint' script.",
        );
    }

    public function testRenderTagsBaseUrlTrailingSlashIsTrimmed(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '/build/',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'src="/build/assets/app-BRBmoGS9.js"',
            $tags,
            "Trailing slash on 'baseUrl' should be trimmed to avoid double slashes.",
        );
        self::assertStringNotContainsString(
            '/build//assets',
            $tags,
            "'baseUrl' should not produce double slashes in asset URLs.",
        );
    }

    public function testRenderTagsCssDeduplication(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/multi-entry-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                    'resources/js/admin.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            1,
            substr_count($tags, 'assets/app-abc123.css'),
            "Shared CSS file should appear only once even when referenced by multiple 'entrypoints'.",
        );
    }

    public function testRenderTagsCssEntrypoint(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/css-entrypoint-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/css/app.css',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            '<link href="/build/assets/app-abc123.css" rel="stylesheet">',
            $tags,
            "Entrypoint whose file ends in '.css' should be rendered as a stylesheet.",
        );
        self::assertStringNotContainsString(
            '<script',
            $tags,
            "CSS-only 'entrypoint' should not produce a script tag.",
        );
    }

    public function testRenderTagsDeduplicatesEntrypoints(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '@web/build',
            ],
        );

        $tags = $vite->renderTags(
            [
                'resources/js/app.js',
                'resources/js/app.js',
            ],
        );

        self::assertSame(
            1,
            substr_count($tags, 'app-BRBmoGS9.js'),
            "Duplicate 'entrypoints' should be deduplicated and rendered only once.",
        );
    }

    public function testRenderTagsDeduplicatesScriptTagsForSameFile(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/duplicate-file-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                    'resources/js/app-legacy.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            1,
            substr_count($tags, 'assets/bundle.js'),
            "Two 'entrypoints' mapping to the same output file should produce only one script tag.",
        );
    }

    public function testRenderTagsDevModeMultipleEntrypoints(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => 'http://localhost:5173',
                'entrypoints' => [
                    'resources/js/app.js',
                    'resources/js/admin.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'http://localhost:5173/resources/js/app.js',
            $tags,
            "Development mode should include the first 'entrypoint'.",
        );
        self::assertStringContainsString(
            'http://localhost:5173/resources/js/admin.js',
            $tags,
            "Development mode should include the second 'entrypoint'.",
        );
    }

    public function testRenderTagsDevModeTrimsLeadingSlashFromEntrypoint(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => 'http://localhost:5173',
                'entrypoints' => [
                    '/resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'http://localhost:5173/resources/js/app.js',
            $tags,
            "Leading slash in 'entrypoint' should be trimmed to avoid double slashes.",
        );
        self::assertStringNotContainsString(
            'http://localhost:5173//resources',
            $tags,
            'Entrypoint should not produce double slashes.',
        );
    }

    public function testRenderTagsDevModeTrimsTrailingSlashFromUrl(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => 'http://localhost:5173/',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'http://localhost:5173/@vite/client',
            $tags,
            'Dev server URL trailing slash should be trimmed to avoid double slashes.',
        );
        self::assertStringNotContainsString(
            'http://localhost:5173//',
            $tags,
            'Dev server URL should not produce double slashes.',
        );
    }

    public function testRenderTagsDevModeWithoutViteClient(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => 'http://localhost:5173',
                'includeViteClient' => false,
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringNotContainsString(
            '@vite/client',
            $tags,
            "Development mode with 'includeViteClient' disabled should not render the '@vite/client' script.",
        );
        self::assertSame(
            '<script type="module" src="http://localhost:5173/resources/js/app.js"></script>',
            $tags,
            "Development mode should still include the 'entrypoint' script tag.",
        );
    }

    public function testRenderTagsExtraDevelopmentTagsHookDefaultReturnsEmptyList(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => 'http://localhost:5173',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            implode(
                "\n",
                [
                    '<script type="module" src="http://localhost:5173/@vite/client"></script>',
                    '<script type="module" src="http://localhost:5173/resources/js/app.js"></script>',
                ],
            ),
            $tags,
            "When no extra tags are configured the development output must contain only the '@vite/client' tag "
            . "and the 'entrypoint' script.",
        );
        self::assertSame(
            'http://localhost:5173',
            $vite->capturedDevServerUrl,
            'The extra tags hook must still be invoked with the resolved dev server URL even when it returns an '
            . 'empty list.',
        );
    }

    public function testRenderTagsFiltersBlankEntrypoints(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '@web/build',
            ],
        );

        $tags = $vite->renderTags(
            [
                'resources/js/app.js',
                '  ',
                '',
            ],
        );

        self::assertStringContainsString(
            '<script type="module"',
            $tags,
            "Blank 'entrypoints' should be filtered out and valid ones should render.",
        );
    }

    public function testRenderTagsForManifestBuild(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            implode(
                "\n",
                [
                    '<link href="/build/assets/app-BRBmoGS9.css" rel="stylesheet">',
                    '<link href="/build/assets/shared-ChJ_j-JJ.css" rel="stylesheet">',
                    '<script type="module" src="/build/assets/app-BRBmoGS9.js"></script>',
                    '<link href="/build/assets/shared-B7PI925R.js" rel="modulepreload">',
                ],
            ),
            $tags,
            "Production mode should render CSS, script, and modulepreload tags from the 'manifest'.",
        );
    }

    public function testRenderTagsHandlesCircularImports(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/circular-import-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            '<script type="module" src="/build/assets/app-circular.js"></script>',
            $tags,
            'Circular imports should be handled without infinite recursion.',
        );
        self::assertSame(
            1,
            substr_count($tags, 'assets/chunk-a.js'),
            'Each circular chunk should appear only once in the output.',
        );
        self::assertSame(
            1,
            substr_count($tags, 'assets/chunk-b.js'),
            'Each circular chunk should appear only once in the output.',
        );
    }

    public function testRenderTagsHandlesDeepImportChain(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/deep-import-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            implode(
                "\n",
                [
                    '<script type="module" src="/build/assets/app-deep.js"></script>',
                    '<link href="/build/assets/chunk-b.js" rel="modulepreload">',
                    '<link href="/build/assets/chunk-c.js" rel="modulepreload">',
                    '<link href="/build/assets/chunk-a.js" rel="modulepreload">',
                ],
            ),
            $tags,
            'Multiple imports with nested sub-imports must be collected in pre-order: each imported chunk is '
            . 'recorded before its own imports are recursively traversed.',
        );
    }

    public function testRenderTagsHandlesNonArrayCssEntry(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/non-array-css-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            '<script type="module" src="/build/assets/app-abc123.js"></script>',
            $tags,
            "Non-array 'css' value on a manifest chunk must be silently skipped by 'collectCssFiles' and "
            . 'produce only the entry script tag, never a stylesheet tag.',
        );
    }

    public function testRenderTagsHandlesNonArrayImports(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/non-array-import-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            '<script type="module" src="/build/assets/app-abc123.js"></script>',
            $tags,
            'Non-array imports value should be silently skipped without errors and produce only the entry script tag.',
        );
    }

    public function testRenderTagsImportedChunkCssFiles(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/multi-entry-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'assets/shared-abc123.css',
            $tags,
            'CSS files from imported chunks should be included in the output.',
        );
    }

    public function testRenderTagsPreloadDeduplication(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/multi-entry-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                    'resources/js/admin.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            1,
            substr_count($tags, 'assets/shared-abc123.js'),
            "Shared imported chunk should have only one 'modulepreload' tag.",
        );
    }

    public function testRenderTagsPreloadSkipsCssChunksAndContinuesWithLaterJsChunks(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/css-chunk-import-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringNotContainsString(
            'shared-styles.css" rel="modulepreload"',
            $tags,
            "Imported chunk whose file ends in '.css' must not be emitted as a 'modulepreload' link.",
        );
        self::assertStringContainsString(
            '<link href="/build/assets/after-css.js" rel="modulepreload">',
            $tags,
            'A JS imported chunk that appears AFTER a CSS chunk in the imports list must still be emitted as a '
            . "'modulepreload', proving the CSS chunk is skipped without aborting the loop.",
        );
    }

    public function testRenderTagsResolvesAssetUrlForLeadingSlashFilePath(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/leading-slash-asset-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertSame(
            implode(
                "\n",
                [
                    '<link href="/build/assets/leading-slash.css" rel="stylesheet">',
                    '<script type="module" src="/build/assets/leading-slash.js"></script>',
                ],
            ),
            $tags,
            "Manifest entries whose 'file' and 'css' paths begin with a leading slash must be ltrimmed so the "
            . 'asset URL never contains a double slash after the base URL.',
        );
    }

    public function testRenderTagsSkipsNonStringCssFile(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/non-string-css-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'assets/app-abc123.css',
            $tags,
            'Valid CSS files should still be rendered when non-string CSS entries are present.',
        );
        self::assertSame(
            1,
            substr_count($tags, 'rel="stylesheet"'),
            "Only valid string CSS entries should produce 'stylesheet' tags.",
        );
    }

    public function testRenderTagsSkipsNonStringImport(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/non-string-import-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            'assets/shared-abc123.js',
            $tags,
            'Valid string imports should still be processed when non-string imports are present.',
        );
    }

    public function testRenderTagsWithModulePreloadDisabled(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
                'modulePreload' => false,
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringNotContainsString(
            'modulepreload',
            $tags,
            "Production mode with 'modulePreload' disabled should not render 'modulepreload' tags.",
        );
        self::assertStringContainsString(
            '<script type="module"',
            $tags,
            "Production mode should still render 'module' script tags.",
        );
    }

    public function testRenderTagsWithMultipleEntrypointsInBuildMode(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/multi-entry-manifest.json',
                'baseUrl' => '@web/build',
                'entrypoints' => [
                    'resources/js/app.js',
                    'resources/js/admin.js',
                ],
            ],
        );

        $tags = $vite->renderTags();

        self::assertStringContainsString(
            '<script type="module" src="/build/assets/app-abc123.js"></script>',
            $tags,
            "Production mode should render the first 'entrypoint' script.",
        );
        self::assertStringContainsString(
            '<script type="module" src="/build/assets/admin-def456.js"></script>',
            $tags,
            "Production mode should render the second 'entrypoint' script.",
        );
        self::assertStringContainsString(
            '<link href="/build/assets/shared-abc123.js" rel="modulepreload">',
            $tags,
            "Shared imported chunk should have a 'modulepreload' tag.",
        );
        self::assertStringContainsString(
            '<link href="/build/assets/utils-def456.js" rel="modulepreload">',
            $tags,
            "Utils imported chunk should have a 'modulepreload' tag.",
        );
    }

    public function testRenderTagsWithStringEntrypointParameter(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'baseUrl' => '@web/build',
            ],
        );

        $tags = $vite->renderTags('resources/js/app.js');

        self::assertStringContainsString(
            '<script type="module" src="/build/assets/app-BRBmoGS9.js"></script>',
            $tags,
            "String 'entrypoint' parameter should be accepted and rendered.",
        );
    }

    public function testThrowInvalidConfigExceptionForAllBlankEntrypoints(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('At least one Vite entrypoint');

        $vite->renderTags(['  ', '']);
    }

    public function testThrowInvalidConfigExceptionForEmptyDevServerUrl(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => '   ',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('devServerUrl');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForEmptyEntrypointsArray(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('At least one Vite entrypoint');

        $vite->renderTags([]);
    }

    public function testThrowInvalidConfigExceptionForInvalidJsonManifest(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/invalid-manifest.json',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unable to decode');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForInvalidManifestChunk(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/invalid-chunk-manifest.json',
                'entrypoints' => [
                    'resources/js/bad.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('is invalid');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForMissingDevServerUrl(): void
    {
        $vite = new Vite(
            [
                'devMode' => true,
                'devServerUrl' => null,
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('devServerUrl');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForMissingManifestEntrypoint(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'entrypoints' => [
                    'resources/js/missing.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('resources/js/missing.js');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForNonArrayManifest(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/scalar-manifest.json',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('must decode to an array');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForNonExistentManifestFile(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/nonexistent.json',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('does not exist');

        $vite->renderTags();
    }

    public function testThrowInvalidConfigExceptionForNonStringEntrypoint(): void
    {
        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Each Vite entrypoint must be a string');

        $vite->renderTags([123]);
    }

    public function testThrowInvalidConfigExceptionForUnreadableManifest(): void
    {
        MockerFunctions::setFileGetContentsShouldFail();

        $vite = new Vite(
            [
                'manifestPath' => '@tests/data/build/.vite/manifest.json',
                'entrypoints' => [
                    'resources/js/app.js',
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unable to read the Vite manifest file');

        $vite->renderTags();
    }
}
