<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use Yii;
use yii\inertia\Manager;
use yii\web\Response;

/**
 * Unit tests for {@see \yii\inertia\Bootstrap}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class BootstrapTest extends TestCase
{
    public function testBeforeSendNormalizesInertiaRedirects(): void
    {
        $this->prepareInertiaRequest('PUT');
        $this->setAbsoluteUrl('/posts');

        $response = Yii::$app->getResponse()->redirect('/target');

        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            303,
            $response->statusCode,
            "PUT redirect should be normalized to '303'.",
        );
        self::assertSame(
            'https://example.test/target',
            $response->getHeaders()->get('Location'),
            'Location header should contain the absolute redirect URL.',
        );
        self::assertFalse(
            $response->getHeaders()->has('X-Redirect'),
            'X-Redirect header should be removed after normalization.',
        );
        self::assertSame(
            'X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should include X-Inertia.',
        );
    }

    public function testBootstrapDoesNotOverrideExistingComponentDefinition(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'inertia' => [
                        'class' => Manager::class,
                        'id' => 'frontend-app',
                    ],
                ],
            ],
        );

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            Manager::class,
            $manager,
            'Inertia component should be an instance of Manager.',
        );
        self::assertSame(
            'frontend-app',
            $manager->id,
            'User-defined component configuration should not be overridden by Bootstrap.',
        );
    }

    public function testBootstrapRegistersAliasAndComponent(): void
    {
        self::assertSame(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src',
            Yii::getAlias('@inertia'),
            '@inertia alias should resolve to the package src/ directory.',
        );
        self::assertInstanceOf(
            Manager::class,
            Yii::$app->get('inertia'),
            'Bootstrap should register the inertia component automatically.',
        );
    }
}
