<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use yii\inertia\MergeProp;

/**
 * Unit tests for {@see MergeProp}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class MergePropTest extends TestCase
{
    public function testAppendWithAssociativeArray(): void
    {
        $merge = (new MergeProp([]))->append(['users' => 'id', 'posts' => 'slug']);

        self::assertSame(
            ['users' => 'id', 'posts' => 'slug'],
            $merge->getAppendPaths(),
            'Append with associative array should map paths to match keys.',
        );
    }

    public function testAppendWithEmptyStringReturnsEmptyPaths(): void
    {
        $merge = (new MergeProp([]))->append('');

        self::assertSame(
            [],
            $merge->getAppendPaths(),
            'Should return an empty array when path is an empty string.',
        );
    }

    public function testAppendWithStringPathAndMatchOn(): void
    {
        $merge = (new MergeProp(['item']))->append('data', 'id');

        self::assertSame(
            ['data' => 'id'],
            $merge->getAppendPaths(),
            'Append with string path and matchOn should produce a single-entry map.',
        );
    }

    public function testAppendWithStringPathWithoutMatchOn(): void
    {
        $merge = (new MergeProp(['item']))->append('data');

        self::assertSame(
            ['data' => ''],
            $merge->getAppendPaths(),
            'Append with string path and no matchOn should produce an empty-string match key.',
        );
    }

    public function testDeepMergeEnablesFlag(): void
    {
        $merge = (new MergeProp([]))->deepMerge();

        self::assertTrue(
            $merge->isDeep(),
            'Should enable the deep flag.',
        );
    }

    public function testDefaultAppendAndPrependPathsAreEmpty(): void
    {
        $merge = new MergeProp([]);

        self::assertEmpty(
            $merge->getAppendPaths(),
            'Default append paths should be empty.',
        );
        self::assertEmpty(
            $merge->getPrependPaths(),
            'Default prepend paths should be empty.',
        );
    }

    public function testDefaultIsNotDeep(): void
    {
        $merge = new MergeProp([]);

        self::assertFalse(
            $merge->isDeep(),
            'Should not be deep by default.',
        );
    }

    public function testGetValueReturnsConstructorValue(): void
    {
        $value = ['users' => [1, 2, 3]];
        $merge = new MergeProp($value);

        self::assertSame(
            $value,
            $merge->getValue(),
            'Should return the value passed to the constructor.',
        );
    }

    public function testPrependWithStringPathAndMatchOn(): void
    {
        $merge = (new MergeProp(['item']))->prepend('messages', 'uuid');

        self::assertSame(
            ['messages' => 'uuid'],
            $merge->getPrependPaths(),
            'Prepend with string path and matchOn should produce a single-entry map.',
        );
    }

    public function testPrependWithStringPathWithoutMatchOn(): void
    {
        $merge = (new MergeProp(['item']))->prepend('data');

        self::assertSame(
            ['data' => ''],
            $merge->getPrependPaths(),
            'Prepend with string path and no matchOn should produce an empty-string match key.',
        );
    }

    public function testReturnNewInstanceWhenSettingAttribute(): void
    {
        $merge = new MergeProp(['item']);

        self::assertNotSame(
            $merge,
            $merge->append(''),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $merge,
            $merge->deepMerge(),
            'Should return a new instance when setting the attribute, ensuring immutability.',
        );
        self::assertNotSame(
            $merge,
            $merge->prepend(''),
            'Should return a new instance when setting prepend paths, ensuring immutability.',
        );
    }
}
