<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use Yii;
use yii\inertia\Manager;
use yii\inertia\tests\providers\BootstrapProvider;
use yii\web\Response;

/**
 * Unit tests for {@see \yii\inertia\Bootstrap}.
 *
 * {@see BootstrapProvider} for test case data providers.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class BootstrapTest extends TestCase
{
    public function testBeforeSendAppendsVaryHeaderWhenAlreadyPresent(): void
    {
        $this->prepareInertiaRequest('PUT');
        $this->setAbsoluteUrl('/posts');

        $response = Yii::$app->getResponse();

        $response->getHeaders()->set('Vary', 'Accept-Encoding');
        $response->setStatusCode(302);

        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            'Accept-Encoding, X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should append X-Inertia to existing values.',
        );
    }

    public function testBeforeSendDoesNotDuplicateVaryHeader(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Yii::$app->getResponse();

        $response->getHeaders()->set('Vary', 'X-Inertia');
        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            'X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should not duplicate X-Inertia when already present.',
        );
    }

    public function testBeforeSendDoesNotDuplicateVaryHeaderWithSpaces(): void
    {
        $this->prepareInertiaRequest();
        $this->setAbsoluteUrl('/dashboard');

        $response = Yii::$app->getResponse();

        $response->getHeaders()->set('Vary', 'Accept-Encoding, X-Inertia');
        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            'Accept-Encoding, X-Inertia',
            $response->getHeaders()->get('Vary'),
            'Vary header should detect X-Inertia even with leading spaces from comma-separated values.',
        );
    }

    public function testBeforeSendDoesNotModifyNonInertiaRequests(): void
    {
        $this->setAbsoluteUrl('/posts');

        $response = Yii::$app->getResponse()->redirect('/target');

        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            302,
            $response->statusCode,
            'Non-Inertia redirect should keep its original status code.',
        );
        self::assertNull(
            $response->getHeaders()->get('Vary'),
            'Non-Inertia response should not set Vary header.',
        );
    }

    #[DataProviderExternal(BootstrapProvider::class, 'redirectNotNormalizedTo303')]
    public function testBeforeSendDoesNotNormalizeRedirectTo303(string $method, int $statusCode): void
    {
        $this->prepareInertiaRequest($method);
        $this->setAbsoluteUrl('/resource');

        $response = Yii::$app->getResponse();

        $response->setStatusCode($statusCode);
        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            $statusCode,
            $response->statusCode,
            "$method request with $statusCode should not be normalized to 303.",
        );
    }

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

    #[DataProviderExternal(BootstrapProvider::class, 'redirectNormalizedTo303')]
    public function testBeforeSendNormalizesRedirectTo303(string $method, int $statusCode): void
    {
        $this->prepareInertiaRequest($method);
        $this->setAbsoluteUrl('/resource');

        $response = Yii::$app->getResponse();

        $response->setStatusCode($statusCode);
        $response->trigger(Response::EVENT_BEFORE_SEND);

        self::assertSame(
            303,
            $response->statusCode,
            "$method request with $statusCode should be normalized to 303.",
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
