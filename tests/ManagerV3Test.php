<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use Yii;
use yii\inertia\Inertia;

/**
 * Unit tests for Inertia v3 protocol features in {@see \yii\inertia\Manager}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class ManagerV3Test extends TestCase
{
    public function testAlwaysPropIncludedDuringPartialReload(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'auth' => Inertia::always(['user' => 'admin']),
                'stats' => ['visits' => 100],
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'auth',
            $props,
            "Key 'auth' should be present during partial reload.",
        );
        self::assertSame(
            ['user' => 'admin'],
            $props['auth'],
            "Key 'auth' should have the correct value.",
        );
        self::assertArrayHasKey(
            'stats',
            $props,
            "Key 'stats' should be present when requested.",
        );
        self::assertArrayNotHasKey(
            'title',
            $props,
            "Key 'title' should be absent when not requested.",
        );
    }

    public function testAlwaysPropNotExcludedWhenListedInPartialExcept(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'auth');

        $response = Inertia::render(
            'Dashboard',
            [
                'auth' => Inertia::always(['user' => 'admin']),
                'stats' => 42,
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'auth',
            $props,
            "Key 'auth' should survive X-Inertia-Partial-Except.",
        );
        self::assertSame(
            ['user' => 'admin'],
            $props['auth'],
            "Key 'auth' should have the correct value.",
        );
    }

    public function testDeepMergePropMetadata(): void
    {
        $this->setAbsoluteUrl('/settings');

        $response = Inertia::render(
            'Settings',
            [
                'config' => Inertia::deepMerge(['theme' => 'dark']),
            ],
        );

        $page = $this->extractPage($response);

        self::assertContains(
            'config',
            $page['mergeProps'] ?? [],
            "Value 'config' should appear in 'mergeProps'.",
        );
        self::assertContains(
            'config',
            $page['deepMergeProps'] ?? [],
            "Value 'config' should appear in 'deepMergeProps'.",
        );
    }

    public function testDeferredPropExcludedOnFirstLoad(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Home',
                'users' => Inertia::defer(fn() => ['Alice', 'Bob']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'title',
            $props,
            "Key 'title' should be present on first load.",
        );
        self::assertArrayNotHasKey(
            'users',
            $props,
            "Key 'users' should be absent on first load.",
        );
    }

    public function testDeferredPropMetadataIncludesGroupAndPath(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'users' => Inertia::defer(fn() => [], 'sidebar'),
                'roles' => Inertia::defer(fn() => [], 'sidebar'),
                'stats' => Inertia::defer(fn() => []),
            ],
        );

        $page = $this->extractPage($response);

        self::assertArrayHasKey(
            'deferredProps',
            $page,
            "Key 'deferredProps' should be present in page payload.",
        );
        self::assertSame(
            ['sidebar' => ['users', 'roles'], 'default' => ['stats']],
            $page['deferredProps'],
            'Deferred props should be grouped with correct dot-notation paths.',
        );
    }

    public function testDeferredPropResolvedOnPartialReload(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'users');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Home',
                'users' => Inertia::defer(fn() => ['Alice', 'Bob']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'users',
            $props,
            "Key 'users' should be present during partial reload.",
        );
        self::assertSame(
            ['Alice', 'Bob'],
            $props['users'],
            "Key 'users' should have the correct value during partial reload.",
        );
    }

    public function testFlashDataIncludedDuringPartialReloadOnly(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');
        Yii::$app->getSession()->setFlash('success', 'Done.');

        $response = Inertia::render(
            'Dashboard',
            [
                'stats' => 42,
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'flash',
            $props,
            "Key 'flash' should be present even when not in X-Inertia-Partial-Data.",
        );
    }

    public function testFlashDataNotExcludedDuringPartialReloadExcept(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'flash');
        Yii::$app->getSession()->setFlash('success', 'Saved.');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'flash',
            $props,
            "Key 'flash' should survive X-Inertia-Partial-Except.",
        );
    }

    public function testFlashIsEmptyArrayWhenNoFlashData(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'flash',
            $props,
            "Key 'flash' should be present in props even when no flash data exists.",
        );
        self::assertEmpty(
            $props['flash'],
            "Key 'flash' should be an empty array when no flash data exists.",
        );
    }

    public function testFlashIsSetInPropsWhenNonEmpty(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getSession()->setFlash('success', 'Saved.');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'flash',
            $props,
            "Key 'flash' should be present in props when session flash data exists.",
        );
        self::assertSame(
            ['success' => 'Saved.'],
            $props['flash'],
            "Key 'flash' should map to the correct values.",
        );
    }

    public function testMergePropMetadata(): void
    {
        $this->setAbsoluteUrl('/users');

        $response = Inertia::render(
            'Users/Index',
            [
                'users' => Inertia::merge(['Alice', 'Bob']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertContains(
            'users',
            $page['mergeProps'] ?? [],
            "Value 'users' should appear in 'mergeProps'.",
        );
        self::assertArrayHasKey(
            'users',
            $props,
            "Key 'users' should be present in props.",
        );
        self::assertSame(
            ['Alice', 'Bob'],
            $props['users'],
            "Key 'users' should map to the correct values.",
        );
    }

    public function testMergePropResetSkipsMetadata(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/users');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Users/Index');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'users');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Reset', 'users');

        $response = Inertia::render(
            'Users/Index',
            [
                'users' => Inertia::merge(['Alice']),
            ],
        );

        $page = $this->extractPage($response);

        self::assertNotContains(
            'users',
            $page['mergeProps'] ?? [],
            "Value 'users' should be absent from 'mergeProps' when reset.",
        );
    }

    public function testMergePropWithAppendPathsMetadata(): void
    {
        $this->setAbsoluteUrl('/users');

        $response = Inertia::render(
            'Users/Index',
            [
                'users' => Inertia::merge(['data' => []])
                    ->append('data', 'id'),
            ],
        );

        $page = $this->extractPage($response);

        self::assertContains(
            'users',
            $page['mergeProps'] ?? [],
            "Value 'users' should appear in 'mergeProps'.",
        );
        self::assertSame(
            ['users.data' => 'id'],
            $page['matchPropsOn'] ?? [],
            "Key 'users.data' should map to 'id' in 'matchPropsOn'.",
        );
    }

    public function testMergePropWithPrependAndMatchOn(): void
    {
        $this->setAbsoluteUrl('/chat');

        $response = Inertia::render(
            'Chat',
            [
                'messages' => Inertia::merge([])->prepend('data', 'uuid'),
            ],
        );

        $page = $this->extractPage($response);

        self::assertContains(
            'messages.data',
            $page['prependProps'] ?? [],
            "Value 'messages.data' should appear in 'prependProps'.",
        );
        self::assertSame(
            ['messages.data' => 'uuid'],
            $page['matchPropsOn'] ?? [],
            "Key 'messages.data' should map to 'uuid' in 'matchPropsOn'.",
        );
    }

    public function testMergePropWithPrependPathsMetadata(): void
    {
        $this->setAbsoluteUrl('/chat');

        $response = Inertia::render(
            'Chat',
            [
                'messages' => Inertia::merge([])->prepend('data'),
            ],
        );

        $page = $this->extractPage($response);

        self::assertContains(
            'messages',
            $page['mergeProps'] ?? [],
            "Value 'messages' should appear in 'mergeProps'.",
        );
        self::assertContains(
            'messages.data',
            $page['prependProps'] ?? [],
            "Value 'messages.data' should appear in 'prependProps'.",
        );
    }

    public function testMultipleOncePropsWhereOneIsSkipped(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/settings');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Settings');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'countries,languages,timezones');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Except-Once-Props', 'countries');

        $response = Inertia::render(
            'Settings',
            [
                'countries' => Inertia::once(fn() => ['US', 'UK']),
                'languages' => Inertia::once(fn() => ['en', 'es']),
                'timezones' => Inertia::once(fn() => ['UTC', 'EST']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayNotHasKey(
            'countries',
            $props,
            "Key 'countries' should be absent when cached by client.",
        );
        self::assertArrayHasKey(
            'languages',
            $props,
            "Key 'languages' should be present.",
        );
        self::assertSame(
            ['en', 'es'],
            $props['languages'] ?? null,
            'Key "languages" should have the correct value.',
        );
        self::assertArrayHasKey(
            'timezones',
            $props,
            "Key 'timezones' should be present.",
        );
        self::assertSame(
            ['UTC', 'EST'],
            $props['timezones'] ?? null,
            'Key "timezones" should have the correct value.',
        );
    }

    public function testMultipleWrapperTypesInSingleRender(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'deferred' => Inertia::defer(fn() => 'deferred-val'),
                'optional' => Inertia::optional(fn() => 'optional-val'),
                'always' => Inertia::always('always-val'),
                'merge' => Inertia::merge(['a', 'b']),
                'once' => Inertia::once(fn() => 'once-val'),
                'regular' => 'plain',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayNotHasKey(
            'deferred',
            $props,
            "Key 'deferred' should be absent on first load.",
        );
        self::assertArrayNotHasKey(
            'optional',
            $props,
            "Key 'optional' should be absent on first load.",
        );
        self::assertArrayHasKey(
            'always',
            $props,
            "Key 'always' should be present.",
        );
        self::assertArrayHasKey(
            'merge',
            $props,
            "Key 'merge' should be present.",
        );
        self::assertArrayHasKey(
            'once',
            $props,
            "Key 'once' should be present.",
        );
        self::assertArrayHasKey(
            'regular',
            $props,
            "Key 'regular' should be present.",
        );
        self::assertSame(
            'always-val',
            $props['always'],
            'Key "always" should have the correct value.',
        );
        self::assertSame(
            ['a', 'b'],
            $props['merge'],
            'Key "merge" should have the correct value.',
        );
        self::assertSame(
            'once-val',
            $props['once'],
            'Key "once" should have the correct value.',
        );
        self::assertSame(
            'plain',
            $props['regular'],
            'Key "regular" should have the correct value.',
        );
    }

    public function testMultipleWrapperTypesResolvedOnPartialReload(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'deferred,optional,once');

        $response = Inertia::render(
            'Dashboard',
            [
                'deferred' => Inertia::defer(fn() => 'deferred-val'),
                'optional' => Inertia::optional(fn() => 'optional-val'),
                'always' => Inertia::always('always-val'),
                'merge' => Inertia::merge(['a']),
                'once' => Inertia::once(fn() => 'once-val'),
                'regular' => 'plain',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertSame(
            'deferred-val',
            $props['deferred'] ?? null,
            "Key 'deferred' should have the correct value.",
        );
        self::assertSame(
            'optional-val',
            $props['optional'] ?? null,
            "Key 'optional' should have the correct value.",
        );
        self::assertSame(
            'always-val',
            $props['always'] ?? null,
            "Key 'always' should have the correct value.",
        );
        self::assertArrayNotHasKey(
            'regular',
            $props,
            "Key 'regular' should be absent when not in partial data.",
        );
    }

    public function testNestedAlwaysPropBypassesPartialReloadFiltering(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'stats');

        $response = Inertia::render(
            'Dashboard',
            [
                'meta' => [
                    'auth' => Inertia::always(['user' => 'admin']),
                    'version' => '1.0',
                ],
                'stats' => 42,
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'meta',
            $props,
            "Key 'meta' should be present as parent of nested always prop.",
        );

        /** @phpstan-var array<string, mixed> $meta */
        $meta = $props['meta'];

        self::assertArrayHasKey(
            'auth',
            $meta,
            "Key 'auth' should be present inside 'meta'.",
        );
        self::assertSame(
            ['user' => 'admin'],
            $meta['auth'],
            "Key 'auth' should have the correct value inside 'meta'.",
        );
    }

    public function testNestedDeferredPropUsesCorrectDotNotationPath(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        $response = Inertia::render(
            'Dashboard',
            [
                'sidebar' => [
                    'users' => Inertia::defer(fn() => [], 'panel'),
                ],
            ],
        );

        $page = $this->extractPage($response);

        self::assertSame(
            ['panel' => ['sidebar.users']],
            $page['deferredProps'] ?? [],
            "Value 'sidebar.users' should use dot-notation in 'deferredProps'.",
        );
    }

    public function testNonAlwaysPropExcludedWhileAlwaysPropIncluded(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Dashboard');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Except', 'secret');

        $response = Inertia::render(
            'Dashboard',
            [
                'auth' => Inertia::always('admin'),
                'secret' => 'hidden',
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'auth',
            $props,
            "Key 'auth' should be present.",
        );
        self::assertArrayNotHasKey(
            'secret',
            $props,
            "Key 'secret' should be absent when listed in except.",
        );
        self::assertArrayHasKey(
            'title',
            $props,
            "Key 'title' should be present.",
        );
    }

    public function testOncePropMetadataIncludesPropField(): void
    {
        $this->setAbsoluteUrl('/settings');

        $response = Inertia::render(
            'Settings',
            [
                'countries' => Inertia::once(fn() => ['US']),
            ],
        );

        $page = $this->extractPage($response);

        self::assertArrayHasKey(
            'onceProps',
            $page,
            "Key 'onceProps' should be present in page payload.",
        );

        /** @phpstan-var array<string, array<string, mixed>> $onceProps */
        $onceProps = $page['onceProps'];

        self::assertArrayHasKey(
            'countries',
            $onceProps,
            "Key 'countries' should be present in 'onceProps'.",
        );
        self::assertSame(
            'countries',
            $onceProps['countries']['prop'] ?? null,
            "Field 'prop' should be 'countries'.",
        );
    }

    public function testOncePropResolvedOnFirstRequest(): void
    {
        $this->setAbsoluteUrl('/settings');

        $response = Inertia::render(
            'Settings',
            [
                'countries' => Inertia::once(fn() => ['US', 'UK']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'countries',
            $props,
            "Key 'countries' should be present on first load.",
        );
        self::assertSame(
            ['US', 'UK'],
            $props['countries'],
            "Key 'countries' should have the correct value on first load.",
        );
        self::assertArrayHasKey(
            'onceProps',
            $page,
            "Key 'onceProps' should be present in page payload.",
        );
        self::assertArrayHasKey(
            'countries',
            $page['onceProps'],
            "Key 'countries' should be present in 'onceProps'.",
        );
    }

    public function testOncePropSkippedWhenClientHasIt(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/settings');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Settings');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'countries');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Except-Once-Props', 'countries');

        $response = Inertia::render(
            'Settings',
            [
                'countries' => Inertia::once(fn() => ['US', 'UK']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayNotHasKey(
            'countries',
            $props,
            "Key 'countries' should be absent when cached by client.",
        );
    }

    public function testOncePropWithCustomKeyAndExpiration(): void
    {
        $this->setAbsoluteUrl('/settings');

        $response = Inertia::render(
            'Settings',
            [
                'countries' => Inertia::once(fn() => ['US'])
                    ->as('countries-v1')
                    ->until(3600),
            ],
        );

        $page = $this->extractPage($response);

        self::assertArrayHasKey('onceProps', $page);

        /** @phpstan-var array<string, array<string, mixed>> $onceProps */
        $onceProps = $page['onceProps'];

        self::assertArrayHasKey(
            'countries-v1',
            $onceProps,
            "Key 'countries-v1' should be present in 'onceProps'.",
        );
        self::assertArrayHasKey(
            'expiresAt',
            $onceProps['countries-v1'],
            "Field 'expiresAt' should be present in 'countries-v1'.",
        );
        self::assertIsInt(
            $onceProps['countries-v1']['expiresAt'],
            "Field 'expiresAt' should be an integer timestamp.",
        );
    }

    public function testOptionalPropExcludedOnFirstLoad(): void
    {
        $this->setAbsoluteUrl('/users');

        $response = Inertia::render('Users/Show', [
            'user' => ['name' => 'Alice'],
            'activity' => Inertia::optional(fn() => ['logged in']),
        ]);

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'user',
            $props,
            "Key 'user' should be present on first load.",
        );
        self::assertArrayNotHasKey(
            'activity',
            $props,
            "Key 'activity' should be absent on first load.",
        );
    }

    public function testOptionalPropResolvedOnPartialReload(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/users');

        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Component', 'Users/Show');
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia-Partial-Data', 'activity');

        $response = Inertia::render(
            'Users/Show',
            [
                'user' => ['name' => 'Alice'],
                'activity' => Inertia::optional(fn() => ['logged in']),
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'activity',
            $props,
            "Key 'activity' should be present during partial reload.",
        );
        self::assertSame(
            ['logged in'],
            $props['activity'],
            "Key 'activity' should have the correct value during partial reload.",
        );
    }

    public function testSessionFlashOverridesSharedFlashWhenNonEmpty(): void
    {
        $this->setAbsoluteUrl('/dashboard');

        Inertia::share('flash', ['info' => 'Shared flash']);

        Yii::$app->getSession()->setFlash('success', 'Session flash');

        $response = Inertia::render(
            'Dashboard',
            [
                'title' => 'Home',
            ],
        );

        $page = $this->extractPage($response);

        $props = $page['props'];

        self::assertArrayHasKey(
            'flash',
            $props,
            "Key 'flash' should be present in props when shared and session 'flash' data exist.",
        );
        self::assertSame(
            ['success' => 'Session flash'],
            $props['flash'],
            "Non-empty session flash 'should' override shared 'flash' value.",
        );
    }
}
