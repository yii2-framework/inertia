<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use RuntimeException;
use Yii;
use yii\inertia\Inertia;
use yii\inertia\Manager;
use yii\inertia\Page;
use yii\web\Response;

/**
 * Unit tests for {@see Manager}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class ManagerTest extends TestCase
{
    public function testLocationReturnsConflictForInertiaRequests(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::location(
            '/login',
        );

        self::assertSame(
            409,
            $response->statusCode,
            "Inertia location should return a '409' status code.",
        );
        self::assertSame(
            'https://example.test/login',
            $response->getHeaders()->get('X-Inertia-Location'),
            'X-Inertia-Location header should contain the absolute redirect URL.',
        );
    }

    public function testLocationReturnsRegularRedirectForStandardRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::location(
            '/login',
        );

        self::assertSame(
            302,
            $response->statusCode,
            "Standard location should return a '302' redirect.",
        );
        self::assertSame(
            'https://example.test/login',
            $response->getHeaders()->get('Location'),
            'Location header should contain the absolute redirect URL.',
        );
    }

    public function testPartialReloadSkipsUnrequestedClosuresAndKeepsErrors(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');
        Yii::$app->getSession()->setFlash('errors', ['name' => ['Name is required.']]);

        Inertia::share(
            'auth.user',
            static function (): array {
                throw new RuntimeException('Shared prop should not be resolved for this partial reload.');
            },
        );

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => static fn(): array => ['visits' => 10],
                'users' => static function (): array {
                    throw new RuntimeException('Page prop should not be resolved for this partial reload.');
                },
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            "Partial reload should include the requested 'stats' prop.",
        );
        self::assertSame(
            ['visits' => 10],
            $props['stats'],
            'Stats prop should contain the resolved closure value.',
        );
        self::assertArrayHasKey(
            'errors',
            $props,
            "Partial reload should always include 'errors' prop.",
        );
        self::assertSame(
            ['name' => ['Name is required.']],
            $props['errors'],
            'Errors prop should contain session flash errors.',
        );
        self::assertArrayNotHasKey(
            'auth',
            $props,
            "Unrequested shared prop 'auth' should be excluded.",
        );
        self::assertArrayNotHasKey(
            'users',
            $props,
            "Unrequested page prop 'users' should be excluded.",
        );
    }

    public function testRenderMapsSessionErrorsAndFlashData(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/profile');

        Yii::$app->getSession()->setFlash('errors', ['email' => ['Email is invalid.']]);
        Yii::$app->getSession()->setFlash('success', 'Profile saved.');

        $response = Inertia::render(
            'Profile/Show',
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'errors',
            $props,
            "Props should contain the 'errors' key.",
        );
        self::assertSame(
            ['email' => ['Email is invalid.']],
            $props['errors'],
            'Errors prop should map session flash errors.',
        );
        self::assertArrayHasKey(
            'flash',
            $page,
            "Page payload should contain the 'flash' key.",
        );
        self::assertSame(
            ['success' => 'Profile saved.'],
            $page['flash'],
            'Flash data should contain remaining session flashes.',
        );
        self::assertSame(
            [],
            Yii::$app->getSession()->getAllFlashes(),
            'Session flashes should be consumed after rendering.',
        );
    }

    public function testRenderReturnsHtmlForStandardRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard?tab=users');

        $response = Inertia::render(
            'Dashboard',
            [
                'users' => [
                    [
                        'id' => 1,
                        'name' => 'Jane',
                    ],
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertSame(
            Response::FORMAT_HTML,
            $response->format,
            'Standard request should return HTML format.',
        );
        self::assertStringContainsString(
            'id="app"',
            (string) $response->content,
            "HTML response should contain the root element with 'id=\"app\"'.",
        );
        self::assertSame(
            'Dashboard',
            $page['component'],
            "Page component should be 'Dashboard'.",
        );
        self::assertArrayHasKey(
            'errors',
            $props,
            "Props should contain the 'errors' key.",
        );
        self::assertEmpty(
            $props['errors'],
            'Errors prop should be empty when no flash errors exist.',
        );
        self::assertSame(
            '/dashboard?tab=users',
            $page['url'],
            'Page URL should match the current request URL.',
        );
        self::assertEmpty(
            $page['version'],
            'Version should be empty when not configured.',
        );
    }

    public function testRenderReturnsJsonForInertiaRequests(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 'build-1';

        Inertia::share(
            'auth.user',
            static fn(): array => [
                'id' => 7,
                'name' => 'Jane',
            ],
        );

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => static fn(): array => [
                    'visits' => 10,
                ],
            ],
        );

        self::assertInstanceOf(
            Page::class,
            $response->data,
            'Response data should be a Page instance for Inertia requests.',
        );
        self::assertSame(
            Response::FORMAT_JSON,
            $response->format,
            'Inertia request should return JSON format.',
        );
        self::assertSame(
            'true',
            $response->getHeaders()->get('X-Inertia'),
            'X-Inertia response header should be "true".',
        );
        self::assertSame(
            'X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should include X-Inertia.',
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertSame(
            'Dashboard',
            $page['component'],
            "Page component should be 'Dashboard'.",
        );
        self::assertArrayHasKey(
            'stats',
            $props,
            "Props should contain the 'stats' key.",
        );
        self::assertSame(
            ['visits' => 10],
            $props['stats'],
            'Stats prop should contain the resolved Closure value.',
        );
        self::assertArrayHasKey(
            'auth',
            $props,
            "Props should contain the shared 'auth' key.",
        );
        self::assertIsArray(
            $props['auth'],
            'Auth prop should be an array.',
        );
        self::assertArrayHasKey(
            'user',
            $props['auth'],
            "Auth prop should contain the 'user' key.",
        );
        self::assertSame(
            ['id' => 7, 'name' => 'Jane'],
            $props['auth']['user'],
            'Auth user prop should contain the resolved shared closure value.',
        );
        self::assertArrayHasKey(
            'errors',
            $props,
            "Props should contain the 'errors' key.",
        );
        self::assertEmpty(
            $props['errors'],
            'Errors prop should be empty when no flash errors exist.',
        );
        self::assertSame(
            'build-1',
            $page['version'],
            'Page version should match the configured version.',
        );
    }

    public function testRenderReturnsVersionConflictForMismatchedGetRequests(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 'build-new';

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Version', 'build-old');
        Yii::$app->getSession()->setFlash('success', 'Saved.');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            409,
            $response->statusCode,
            "Version mismatch should return a '409' status code.",
        );
        self::assertSame(
            'https://example.test/dashboard',
            $response->getHeaders()->get('X-Inertia-Location'),
            'X-Inertia-Location header should contain the current absolute URL.',
        );
        self::assertSame(
            'Saved.',
            Yii::$app->getSession()->getFlash('success'),
            'Session flashes should be preserved after a version conflict redirect.',
        );
    }
}
