<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\inertia\Inertia;
use yii\inertia\Manager;
use yii\web\Response;

/**
 * Unit tests for {@see Inertia} static facade.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class InertiaTest extends TestCase
{
    public function testFlushSharedRemovesAllSharedProps(): void
    {
        Inertia::share('key', 'value');

        self::assertSame(
            'value',
            Inertia::getShared('key'),
            'Shared prop should be set before flush.',
        );

        Inertia::flushShared();

        self::assertEmpty(
            Inertia::getShared(),
            'All shared props should be removed after flush.'
        );
    }

    public function testGetSharedReturnsAllSharedProps(): void
    {
        Inertia::share('locale', 'en');
        Inertia::share('theme', 'dark');

        $shared = Inertia::getShared();

        self::assertIsArray(
            $shared,
            "With 'null' key should return an array.",
        );
        self::assertArrayHasKey(
            'locale',
            $shared,
            "Shared props should contain 'locale'.",
        );
        self::assertArrayHasKey(
            'theme',
            $shared,
            "Shared props should contain 'theme'.",
        );
        self::assertSame(
            'en',
            $shared['locale'],
            'Locale value should match.',
        );
        self::assertSame(
            'dark',
            $shared['theme'],
            'Theme value should match.',
        );
    }

    public function testGetSharedReturnsNestedValue(): void
    {
        Inertia::share('auth.user.name', 'Jane');

        self::assertSame(
            'Jane',
            Inertia::getShared('auth.user.name'),
            'Should return the nested value at the given dot-notation key.',
        );
    }

    public function testGetSharedReturnsDefaultWhenKeyNotFound(): void
    {
        self::assertSame(
            'fallback',
            Inertia::getShared('nonexistent', 'fallback'),
            'Should return the default value when key does not exist.',
        );
    }

    public function testGetVersionReturnsEmptyStringByDefault(): void
    {
        self::assertSame(
            '',
            Inertia::getVersion(),
            'Should return empty string when no version is configured.',
        );
    }

    public function testGetVersionReturnsConfiguredVersion(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 'build-42';

        self::assertSame(
            'build-42',
            Inertia::getVersion(),
            'Should return the configured version string.',
        );
    }

    public function testLocationReturnsResponseForStandardRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::location('/login');

        self::assertSame(
            302,
            $response->statusCode,
            "Standard request should return a '302' redirect.",
        );
    }

    public function testLocationReturns409ForInertiaRequests(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::location('/login');

        self::assertSame(
            409,
            $response->statusCode,
            "Inertia request should return a '409' status code.",
        );
        self::assertSame(
            'https://example.test/login',
            $response->getHeaders()->get('X-Inertia-Location'),
            'X-Inertia-Location header should contain the absolute redirect URL.',
        );
    }

    public function testRenderReturnsHtmlForStandardRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render('Dashboard', ['key' => 'value']);

        self::assertSame(
            Response::FORMAT_HTML,
            $response->format,
            'Standard request should return HTML format.',
        );
    }

    public function testRenderReturnsJsonForInertiaRequests(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render('Dashboard', ['key' => 'value']);

        self::assertSame(
            Response::FORMAT_JSON,
            $response->format,
            'Inertia request should return JSON format.',
        );
    }

    public function testShareWithSingleKey(): void
    {
        Inertia::share('app.name', 'MyApp');

        self::assertSame(
            'MyApp',
            Inertia::getShared('app.name'),
            'With single key should store the value.',
        );
    }

    public function testShareWithArray(): void
    {
        Inertia::share(['locale' => 'en', 'debug' => true]);

        self::assertSame(
            'en',
            Inertia::getShared('locale'),
            "With array should store locale.",
        );
        self::assertTrue(
            Inertia::getShared('debug'),
            'With array should store debug.',
        );
    }

    public function testManagerThrowsExceptionForInvalidComponent(): void
    {
        $this->destroyApplication();
        $this->mockWebApplication(
            [
                'components' => [
                    'inertia' => [
                        'class' => stdClass::class,
                    ],
                ],
            ],
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'The "inertia" application component must be an instance of ' . Manager::class . '.',
        );

        Inertia::getVersion();
    }

    public function testShareWithArrayStoresOnlyDeclaredKeys(): void
    {
        Inertia::share(['locale' => 'en', 'debug' => true]);

        $shared = Inertia::getShared();

        self::assertSame(
            ['locale' => 'en', 'debug' => true],
            $shared,
            'Array share should store exactly the declared keys without side effects.',
        );
    }
}
