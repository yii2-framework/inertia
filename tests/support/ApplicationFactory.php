<?php

declare(strict_types=1);

namespace yii\inertia\tests\support;

use Yii;
use yii\helpers\ArrayHelper;
use yii\inertia\Bootstrap;

/**
 * Creates Yii application instances for tests.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class ApplicationFactory
{
    private const COOKIE_VALIDATION_KEY = 'test-cookie-validation-key';

    /**
     * Destroys the current application.
     */
    public static function destroy(): void
    {
        if (Yii::$app !== null && Yii::$app->has('session', true)) {
            Yii::$app->session->close();
        }

        Yii::$app = null; // @phpstan-ignore assign.propertyType (Yii2 test teardown pattern)
    }

    /**
     * Creates a web application with the Inertia bootstrap configured.
     *
     * @param array<string, mixed> $override
     */
    public static function web(array $override = []): void
    {
        new \yii\web\Application(ArrayHelper::merge(self::commonBase(), $override));
    }

    /**
     * @return array<string, mixed>
     */
    private static function commonBase(): array
    {
        return [
            'id' => 'testapp',
            'basePath' => dirname(__DIR__),
            'vendorPath' => self::resolveVendorPath(),
            'controllerNamespace' => 'yii\inertia\tests\data\controllers',
            'bootstrap' => [Bootstrap::class],
            'aliases' => [
                '@root' => dirname(__DIR__, 2),
                '@tests' => dirname(__DIR__),
            ],
            'components' => [
                'request' => [
                    'cookieValidationKey' => self::COOKIE_VALIDATION_KEY,
                    'hostInfo' => 'https://example.test',
                    'scriptFile' => dirname(__DIR__, 2) . '/index.php',
                    'scriptUrl' => '/index.php',
                    'isConsoleRequest' => false,
                ],
            ],
        ];
    }

    private static function resolveVendorPath(): string
    {
        $candidates = [
            dirname(__DIR__, 2) . '/vendor',
            dirname(__DIR__, 3) . '/core/vendor',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return dirname(__DIR__, 2) . '/vendor';
    }
}
