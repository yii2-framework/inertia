<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use yii\inertia\Page;
use yii\inertia\tests\providers\PageProvider;

/**
 * Unit tests for {@see Page}.
 *
 * {@see PageProvider} for test case data providers.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class PageTest extends TestCase
{
    public function testClearHistoryTrueIncludedInOutput(): void
    {
        $page = (new Page('Home', [], '/'))
            ->withClearHistory();

        $serialized = $page->jsonSerialize();

        self::assertArrayHasKey(
            'clearHistory',
            $serialized,
            "Should appear when 'true'.",
        );
        self::assertTrue(
            $serialized['clearHistory'],
            "Should be 'true'.",
        );
        self::assertArrayNotHasKey(
            'encryptHistory',
            $serialized,
            "Should not appear when 'false'.",
        );
    }

    public function testDefaultClearHistoryIsFalseAndOmittedFromOutput(): void
    {
        $page = new Page('Home', [], '/');

        $serialized = $page->jsonSerialize();

        self::assertArrayNotHasKey(
            'clearHistory',
            $serialized,
            'Should not appear in serialized output when false.',
        );
    }

    public function testDefaultEncryptHistoryIsFalseAndOmittedFromOutput(): void
    {
        $page = new Page('Home', [], '/');

        $serialized = $page->jsonSerialize();

        self::assertArrayNotHasKey(
            'encryptHistory',
            $serialized,
            "Should not appear in serialized output when 'false'.",
        );
    }

    public function testEncryptHistoryTrueIncludedInOutput(): void
    {
        $page = (new Page('Home', [], '/'))
            ->withEncryptHistory();

        $serialized = $page->jsonSerialize();

        self::assertArrayNotHasKey(
            'clearHistory',
            $serialized,
            "Should not appear when 'false'.",
        );
        self::assertArrayHasKey(
            'encryptHistory',
            $serialized,
            "Should appear when 'true'.",
        );
        self::assertTrue(
            $serialized['encryptHistory'],
            "Should be 'true'.",
        );
    }

    /**
     * @param array{
     *   component: string,
     *   props: array<string, mixed>,
     *   url: string,
     *   version: int|string,
     *   flash: array<string, mixed>,
     *   clearHistory: bool,
     *   encryptHistory: bool,
     * } $input
     * @param array<string, mixed> $expected
     */
    #[DataProviderExternal(PageProvider::class, 'jsonSerialize')]
    public function testJsonSerialize(array $input, array $expected): void
    {
        $page = (new Page($input['component'], $input['props'], $input['url'], $input['version']))
            ->withFlash($input['flash'])
            ->withClearHistory($input['clearHistory'])
            ->withEncryptHistory($input['encryptHistory']);

        self::assertSame(
            $expected,
            $page->jsonSerialize(),
            'Page serialization should match the expected payload.',
        );
    }

    public function testReturnNewInstanceWhenSettingAttribute(): void
    {
        $page = new Page('Home', [], '/');

        self::assertNotSame(
            $page,
            $page->withClearHistory(),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withDeepMergeProps([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withDeferredProps([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withEncryptHistory(),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withFlash([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withMatchPropsOn([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withMergeProps([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withOnceProps([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withPrependProps([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $page,
            $page->withScrollProps([]),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
    }

    public function testScrollPropsIncludedWhenNonEmpty(): void
    {
        $page = (new Page('Feed', [], '/feed'))
            ->withScrollProps(['posts' => ['pageName' => 'page', 'currentPage' => 1, 'nextPage' => 2]]);

        $serialized = $page->jsonSerialize();

        self::assertArrayHasKey(
            'scrollProps',
            $serialized,
            "Key 'scrollProps' should appear when non-empty.",
        );
        self::assertSame(
            ['posts' => ['pageName' => 'page', 'currentPage' => 1, 'nextPage' => 2]],
            $serialized['scrollProps'],
        );
    }

    public function testScrollPropsOmittedWhenEmpty(): void
    {
        $page = new Page('Home', [], '/');

        $serialized = $page->jsonSerialize();

        self::assertArrayNotHasKey(
            'scrollProps',
            $serialized,
            "Key 'scrollProps' should not appear when empty.",
        );
    }
}
