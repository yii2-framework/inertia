<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use RuntimeException;
use Yii;
use yii\inertia\Inertia;
use yii\inertia\Manager;
use yii\inertia\Page;
use yii\web\Request;
use yii\web\Response;

/**
 * Unit tests for {@see Manager}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class ManagerTest extends TestCase
{
    public function testClosurePropsWithRequestParameter(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'current_url' => static fn(Request $request): string => $request->getUrl(),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'current_url',
            $props,
            'Props should contain the closure-resolved key.',
        );
        self::assertSame(
            '/dashboard',
            $props['current_url'],
            'Closure accepting Request should receive the current request and resolve its URL.',
        );
    }

    public function testFlushSharedRemovesAllSharedProps(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->share('key', 'value');
        $manager->flushShared();

        self::assertEmpty(
            $manager->getShared(),
            'Should remove all shared props.',
        );
    }

    public function testGetVersionResolvesClosureWithRequest(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $this->setAbsoluteUrl('/versioned');

        $manager->version = static fn(Request $request): string => $request->getUrl();

        self::assertSame(
            '/versioned',
            $manager->getVersion(),
            'Should pass the current request to request-aware version callbacks.',
        );
    }

    public function testGetVersionReturnsEmptyStringForNull(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = null;

        self::assertEmpty(
            $manager->getVersion(),
            "Should return empty string when version is 'null'.",
        );
    }

    public function testGetVersionReturnsIntegerDirectly(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 42;

        self::assertSame(
            42,
            $manager->getVersion(),
            "Should return 'integer' version directly.",
        );
    }

    public function testIsInertiaRequestRespectsExplicitRequestOverCurrent(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        // mark the current app request as Inertia.
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia', 'true');

        self::assertTrue(
            $manager->isInertiaRequest(),
            'Current request should be Inertia.',
        );

        // create a fresh non-Inertia request explicitly.
        $nonInertiaRequest = new Request();

        $nonInertiaRequest->cookieValidationKey = 'test';

        self::assertFalse(
            $manager->isInertiaRequest($nonInertiaRequest),
            'Explicit non-Inertia request should override the current Inertia app request.',
        );
    }

    public function testIsInertiaRequestReturnsFalseForStandardRequests(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );
        self::assertFalse(
            $manager->isInertiaRequest(),
            'Standard request without X-Inertia header should not be an Inertia request.',
        );
    }

    public function testIsInertiaRequestReturnsTrueWithExplicitRequest(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $request = Yii::$app->getRequest();

        $request->getHeaders()->set('X-Inertia', 'true');

        self::assertTrue(
            $manager->isInertiaRequest($request),
            'Request with X-Inertia header should be recognized as an Inertia request.',
        );
    }

    public function testIsInertiaRequestUsesCurrentRequestWhenNullProvided(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia', 'true');

        self::assertTrue(
            $manager->isInertiaRequest(null),
            "Passing 'null' should fallback to the current application request.",
        );
    }

    public function testLocationAppendsVaryHeaderWhenAlreadyPresent(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getResponse()->getHeaders()->set('Vary', 'Accept-Encoding');

        $response = Inertia::location(
            '/login',
        );

        self::assertSame(
            'Accept-Encoding, X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should append X-Inertia to existing values.',
        );
    }

    public function testLocationRedirectDoesNotSetXRedirectHeaderForAjaxRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        // Set AJAX header but NOT Inertia header — this is a standard AJAX request.
        Yii::$app->getRequest()->getHeaders()->set('X-Requested-With', 'XMLHttpRequest');

        $response = Inertia::location(
            '/login',
        );

        self::assertSame(
            302,
            $response->statusCode,
            "Non-Inertia AJAX request should return '302'.",
        );
        self::assertNull(
            $response->getHeaders()->get('X-Redirect'),
            "Non-Inertia location redirect should not set X-Redirect header ('checkAjax' must be 'false').",
        );
        self::assertSame(
            'https://example.test/login',
            $response->getHeaders()->get('Location'),
            'Location header should be set directly, not via X-Redirect.',
        );
    }

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
        self::assertSame(
            'X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should include X-Inertia on location conflict response.',
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

    public function testPartialReloadDistinguishesSimilarPropNames(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'statsExtra' => [
                    'extra' => true,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            "'stats' should be included in partial reload.",
        );
        self::assertArrayNotHasKey(
            'statsExtra',
            $props,
            "'statsExtra' should not match 'stats' partial data — dot notation boundary required.",
        );
    }

    public function testPartialReloadDoesNotLeakExcludedPropsAsNull(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'title');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Hello',
                'secret' => static fn(): string => 'should-never-appear',
            ],
        );

        $page = $this->extractPage($response);

        self::assertSame(
            ['title' => 'Hello', 'errors' => [], 'flash' => []],
            $page['props'],
            'Excluded props must not leak as null or resolved values.',
        );
    }

    public function testPartialReloadDropsFilteredParentNotInOnlyList(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render('Dashboard', [
            'stats' => ['visits' => 10],
            'settings' => ['theme' => 'dark', 'lang' => 'en'],
        ]);

        $page = $this->extractPage($response);

        self::assertSame(
            ['stats' => ['visits' => 10], 'errors' => [], 'flash' => []],
            $page['props'],
            'Parent "settings" with filtered children should be dropped when not in only list.',
        );
    }

    public function testPartialReloadDropsUnrequestedEmptyProp(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'filters' => [],
                'stats' => [
                    'visits' => 10,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayNotHasKey(
            'filters',
            $props,
            "Unrequested empty prop 'filters' should be excluded from partial reload.",
        );
        self::assertArrayHasKey(
            'stats',
            $props,
            'Requested prop should be included.',
        );
    }

    public function testPartialReloadExceptDoesNotExcludeErrors(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'errors');
        Yii::$app->getSession()->setFlash('errors', ['name' => ['Required.']]);

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'errors',
            $props,
            'Errors should never be excluded even when listed in X-Inertia-Partial-Except.',
        );
        self::assertSame(
            ['name' => ['Required.']],
            $props['errors'],
            'Errors content should be preserved.',
        );
    }

    public function testPartialReloadExceptDoesNotLeakExcludedPropAsNull(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'secret');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Hello',
                'secret' => static fn(): string => 'should-never-appear',
            ],
        );

        $page = $this->extractPage($response);

        self::assertSame(
            ['title' => 'Hello', 'errors' => [], 'flash' => []],
            $page['props'],
            'Except-excluded props must not leak as null or resolved values.',
        );
    }

    public function testPartialReloadExceptOnlyDropsEmptyParent(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'auth.user');

        $response = Inertia::render(
            'Dashboard',
            [
                'auth' => [
                    'user' => [
                        'id' => 1,
                        'name' => 'Jane',
                    ],
                ],
                'stats' => ['visits' => 10],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayNotHasKey(
            'auth',
            $props,
            "Empty parent 'auth' should be dropped in except-only reload to avoid overwriting client cache.",
        );
        self::assertArrayHasKey(
            'stats',
            $props,
            'Non-excluded props should remain.',
        );
    }

    public function testPartialReloadExceptOnlyPreservesEmptyErrors(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'errors',
            $props,
            'Empty errors should be preserved in except-only partial reload.',
        );
        self::assertEmpty(
            $props['errors'],
            'Errors should be an empty array when no flash errors exist.',
        );
    }

    public function testPartialReloadExcludesEmptyNestedPropNotInOnlyList(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'metadata' => [
                    'tags' => [],
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            'Requested prop should be included.',
        );
        self::assertArrayNotHasKey(
            'metadata',
            $props,
            'Empty nested prop not in the only list should be excluded.',
        );
    }

    public function testPartialReloadIgnoredForNonInertiaRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'users',
            $props,
            'Non-Inertia request should include all props regardless of partial headers.',
        );
    }

    public function testPartialReloadIgnoredWhenComponentDoesNotMatch(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'OtherComponent');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            'All props should be present when component does not match.',
        );
        self::assertArrayHasKey(
            'users',
            $props,
            'All props should be present when component does not match.',
        );
    }

    public function testPartialReloadKeepsExactlyRequestedEmptyProp(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'filters');

        $response = Inertia::render(
            'Dashboard',
            [
                'filters' => [],
                'stats' => [
                    'visits' => 10,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'filters',
            $props,
            "Exactly requested empty prop 'filters' should be kept.",
        );
        self::assertEmpty(
            $props['filters'],
            'Empty prop should remain an empty array.',
        );
        self::assertArrayNotHasKey(
            'stats',
            $props,
            'Unrequested prop should be excluded.',
        );
    }

    public function testPartialReloadPreservesEmptyParentWhenChildRequested(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'auth.user');

        $response = Inertia::render(
            'Dashboard',
            [
                'auth' => [],
                'stats' => [
                    'visits' => 10,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'auth',
            $props,
            "Empty parent 'auth' should be preserved when child 'auth.user' is requested.",
        );
        self::assertEmpty(
            $props['auth'],
            'Empty parent should remain an empty array.',
        );
        self::assertArrayNotHasKey(
            'stats',
            $props,
            "Unrequested top-level prop 'stats' should be excluded.",
        );
    }

    public function testPartialReloadPreservesFilteredEmptyParentWhenChildPathRequested(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'auth.user');

        $response = Inertia::render(
            'Dashboard',
            [
                'auth' => ['permissions' => ['admin']],
                'stats' => ['visits' => 10],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'auth',
            $props,
            "Parent 'auth' should be preserved when child path 'auth.user' is in the only list.",
        );
        self::assertSame(
            [],
            $props['auth'],
            'Auth should be empty after filtering excluded non-matching children.',
        );
        self::assertArrayNotHasKey(
            'stats',
            $props,
            'Unrequested top-level prop should be excluded.',
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

    public function testPartialReloadWithBothDataAndExceptHeaders(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats,users');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'users');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            'Stats should be included (in data, not in except).',
        );
        self::assertArrayNotHasKey(
            'users',
            $props,
            'Users should be excluded (in except overrides data).',
        );
        self::assertArrayNotHasKey(
            'settings',
            $props,
            'Settings should be excluded (not in data).',
        );
    }

    public function testPartialReloadWithExceptHeaderExcludesSpecifiedProps(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'users');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                    'John',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            "Props should include 'stats' when not in except list.",
        );
        self::assertSame(
            ['visits' => 10],
            $props['stats'],
            'Stats prop should be preserved.',
        );
        self::assertArrayNotHasKey(
            'users',
            $props,
            "Props should exclude 'users' from except list.",
        );
        self::assertArrayHasKey(
            'errors',
            $props,
            'Errors should always be present regardless of except list.',
        );
    }

    public function testPartialReloadWithNestedDotNotationProps(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'auth.user');

        Inertia::share(
            'auth',
            [
                'user' => [
                    'id' => 1,
                    'name' => 'Jane',
                ],
                'permissions' => [
                    'admin',
                ],
            ],
        );

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'auth',
            $props,
            "Partial data 'auth.user' should include 'auth' parent.",
        );
        self::assertIsArray(
            $props['auth'],
            'Auth prop should be an array.',
        );
        self::assertArrayHasKey(
            'user',
            $props['auth'],
            "Auth should include 'user' child.",
        );
        self::assertSame(
            ['id' => 1, 'name' => 'Jane'],
            $props['auth']['user'],
            'Auth user should contain the shared value.',
        );
    }

    public function testPartialReloadWithOnlyExceptHeaderAndNoDataHeader(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'users');

        $response = Inertia::render(
            'Dashboard',
            [
                'filters' => [],
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'filters',
            $props,
            'Legitimately empty prop "filters" should be preserved in except-only reload.',
        );
        self::assertSame(
            [],
            $props['filters'],
            'Empty filters prop should remain an empty array.',
        );
        self::assertArrayHasKey(
            'stats',
            $props,
            'Stats should be included when only except header is set.',
        );
        self::assertArrayNotHasKey(
            'users',
            $props,
            'Users should be excluded by the except header.',
        );
    }

    public function testPartialReloadWithSpacesInHeaderValues(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', ' stats , users ');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            "Trimmed header value 'stats' should be included.",
        );
        self::assertArrayHasKey(
            'users',
            $props,
            "Trimmed header value 'users' should be included.",
        );
        self::assertArrayNotHasKey(
            'settings',
            $props,
            'Props not in the partial data list should be excluded.',
        );
    }

    public function testPartialReloadWithWhitespaceOnlyHeaderTreatedAsEmpty(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', '   ');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 10,
                ],
                'users' => [
                    'Jane',
                ],
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'stats',
            $props,
            'Whitespace-only partial data header should be treated as empty (include all).',
        );
        self::assertArrayHasKey(
            'users',
            $props,
            'All props should be present when partial data header is whitespace-only.',
        );
    }

    public function testRenderAppendsVaryHeaderWhenAlreadyPresent(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getResponse()->getHeaders()->set('Vary', 'Accept-Language');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            'Accept-Language, X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should append X-Inertia to existing values during render.',
        );
    }

    public function testRenderDoesNotDuplicateVaryWithSpacedTokens(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getResponse()->getHeaders()->set('Vary', 'Accept-Encoding, X-Inertia');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            'Accept-Encoding, X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should detect X-Inertia even with leading spaces from comma-separated values.',
        );
    }

    public function testRenderFlashesAreConsumedAfterReading(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getSession()->setFlash('success', 'Done.');
        Yii::$app->getSession()->setFlash('errors', ['field' => ['Required.']]);

        $response = Inertia::render(
            'Dashboard',
        );

        $page = $this->extractPage($response);

        // flashes should have been consumed (`getAllFlashes(true)` deletes them).
        self::assertSame(
            [],
            Yii::$app->getSession()->getAllFlashes(),
            'All flashes should be consumed after render (getAllFlashes must pass true to delete).',
        );

        // verify flash data was correctly split.
        $props = $page['props'];

        self::assertArrayHasKey(
            'errors',
            $props,
            'Errors should be extracted from flashes.',
        );
        self::assertSame(
            ['field' => ['Required.']],
            $props['errors'],
            'Errors content should match flash data.',
        );
        self::assertArrayHasKey(
            'flash',
            $page,
            'Non-error flashes should appear in flash key.',
        );
        self::assertSame(
            ['success' => 'Done.'],
            $page['flash'],
            'Flash data should contain remaining non-error flashes.',
        );
    }

    public function testRenderMapsNonArrayErrorFlashToArray(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/profile');

        Yii::$app->getSession()->setFlash('errors', 'Single error message');

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
        self::assertIsArray(
            $props['errors'],
            'Non-array error flash should be cast to an array.',
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

    public function testRenderPassesViewDataToRootView(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [],
            [
                'customKey' => 'customValue',
            ],
        );

        self::assertStringContainsString(
            'id="app"',
            (string) $response->content,
            'HTML response should contain root element even with custom viewData.',
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
        self::assertNull(
            $response->data,
            "Response data should be 'null' for HTML responses.",
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
        self::assertSame(
            'X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should be set for HTML responses.',
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
        self::assertNull(
            $response->content,
            'Response content should be null for JSON responses.',
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
            "Auth 'user' prop should contain the resolved shared Closure value.",
        );
        self::assertArrayHasKey(
            'errors',
            $props,
            "Props should contain the 'errors' key.",
        );
        self::assertSame(
            [],
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
            'X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should be set on version conflict response.',
        );
        self::assertSame(
            '',
            $response->content,
            'Response content should be empty on version conflict.',
        );
        self::assertNull($response->data, 'Response data should be null on version conflict.');
        self::assertSame(
            'Saved.',
            Yii::$app->getSession()->getFlash('success'),
            'Session flashes should be preserved after a version conflict redirect.',
        );
    }

    public function testRenderWithoutSessionReturnsEmptyErrorsAndNoFlash(): void
    {
        $this->destroyApplication();
        $this->mockWebApplicationWithoutSession();
        $_SERVER['REQUEST_METHOD'] = 'GET';

        self::assertFalse(
            Yii::$app->has('session', false),
            'Session component should not be registered.',
        );

        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'key' => 'value',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'errors',
            $props,
            'Errors should be present even without session.',
        );
        self::assertSame(
            [],
            $props['errors'],
            'Errors should be empty when session is absent.',
        );
        self::assertArrayNotHasKey(
            'flash',
            $page,
            'Flash should not be present when session is absent.',
        );
    }

    public function testRenderWithViewDataMergesIntoView(): void
    {
        $this->destroyApplication();
        $this->mockWebApplication(
            [
                'components' => [
                    'inertia' => [
                        'class' => Manager::class,
                        'rootView' => '@tests/data/views/custom-app.php',
                    ],
                ],
            ],
        );
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [],
            [
                'title' => 'My Title',
            ],
        );

        self::assertStringContainsString(
            'My Title',
            (string) $response->content,
            'Custom viewData should be available in the root view template.',
        );
    }

    public function testShareAndGetShared(): void
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->share('app.name', 'TestApp');

        self::assertSame(
            'TestApp',
            $manager->getShared('app.name'),
            'Should return the shared value at the given key.',
        );
        self::assertIsArray(
            $manager->getShared(),
            "With 'null' key should return all shared props.",
        );
        self::assertSame(
            'default',
            $manager->getShared('nonexistent', 'default'),
            'Should return the default when key does not exist.',
        );
    }

    public function testVersionConflictNotTriggeredForNonInertiaRequests(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 'build-new';

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Version', 'build-old');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            Response::FORMAT_HTML,
            $response->format,
            'Non-Inertia request should not trigger version conflict.',
        );
    }

    public function testVersionConflictNotTriggeredForPostRequests(): void
    {
        $this->prepareInertiaRequest('POST');
        $this->setAbsoluteUrl('/dashboard');

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 'build-new';

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Version', 'build-old');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertNotSame(
            409,
            $response->statusCode,
            'POST request should not trigger version conflict even with mismatched version.',
        );
    }

    public function testVersionConflictNotTriggeredWhenVersionsMatch(): void
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

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Version', 'build-1');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertNotSame(
            409,
            $response->statusCode,
            'Matching versions should not trigger a version conflict.',
        );
    }

    public function testVersionConflictPreservesMultipleFlashes(): void
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
        Yii::$app->getSession()->setFlash('warning', 'Check email.');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            409,
            $response->statusCode,
            "Version mismatch should return '409'.",
        );
        self::assertSame(
            'Saved.',
            Yii::$app->getSession()->getFlash('success'),
            'Success flash should be preserved after version conflict.',
        );
        self::assertSame(
            'Check email.',
            Yii::$app->getSession()->getFlash('warning'),
            'Warning flash should be preserved after version conflict.',
        );
    }

    public function testVersionConflictWithIntegerVersion(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 42;

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Version', '99');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            409,
            $response->statusCode,
            "Integer version mismatch should trigger a '409' conflict.",
        );
    }

    public function testVersionConflictWithMatchingIntegerVersion(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be a Manager instance.',
        );

        $manager->version = 42;

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Version', '42');

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertNotSame(
            409,
            $response->statusCode,
            'Matching integer version (as string) should not trigger conflict.',
        );
    }

    public function testVersionConflictWithoutSessionDoesNotFail(): void
    {
        $this->destroyApplication();
        $this->mockWebApplicationWithoutSession();
        $_SERVER['REQUEST_METHOD'] = 'GET';

        self::assertFalse(
            Yii::$app->has('session', false),
            'Session component should not be registered.',
        );

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

        $response = Inertia::render(
            'Dashboard',
        );

        self::assertSame(
            409,
            $response->statusCode,
            'Version conflict should work even without session component.',
        );
    }

    public function testZeroParamClosureReturnsValueDirectly(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'timestamp' => static fn(): int => 1234567890,
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'timestamp',
            $props,
            'Zero-param closure should be resolved.',
        );
        self::assertSame(
            1234567890,
            $props['timestamp'],
            'Zero-param closure should return its value directly without receiving a Request.',
        );
    }
}
