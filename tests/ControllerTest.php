<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use Yii;
use yii\web\Response;

/**
 * Unit tests for {@see \yii\inertia\web\Controller}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class ControllerTest extends TestCase
{
    public function testInertiaControllerHelperRendersPage(): void
    {
        $this->setAbsoluteUrl('/site/index');

        $response = Yii::$app->runAction('site/index');

        self::assertInstanceOf(
            Response::class,
            $response,
            'Should return a Response instance.',
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
            ['visits' => 42],
            $props['stats'],
            'Stats prop should match the controller data.',
        );
    }

    public function testLocationControllerHelperUsesInertiaLocationResponses(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/site/external');

        $response = Yii::$app->runAction('site/external');

        self::assertInstanceOf(
            Response::class,
            $response,
            'Should return a Response instance.',
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
}
