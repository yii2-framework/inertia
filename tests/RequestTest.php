<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use Yii;
use yii\helpers\ArrayHelper;
use yii\inertia\tests\support\stub\MockerFunctions;
use yii\inertia\web\Request;

/**
 * Unit tests for {@see Request}.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class RequestTest extends TestCase
{
    public function testCsrfCookieHttpOnlyIsFalse(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(
            Request::class,
            $request,
            "Request component should be an instance of 'yii\inertia\web\Request'.",
        );
        self::assertFalse(
            $request->csrfCookie['httpOnly'] ?? true,
            "Key 'httpOnly' should be 'false' so JavaScript can read the 'cookie'.",
        );
    }

    public function testCsrfHeaderDefaultsToXXsrfToken(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(
            Request::class,
            $request,
            "Request component should be an instance of 'yii\inertia\web\Request'.",
        );
        self::assertSame(
            'X-XSRF-TOKEN',
            $request->csrfHeader,
            "CSRF 'header' should default to X-XSRF-TOKEN.",
        );
    }

    public function testCsrfParamDefaultsToXsrfToken(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(
            Request::class,
            $request,
            "Request component should be an instance of 'yii\inertia\web\Request'.",
        );
        self::assertSame(
            'XSRF-TOKEN',
            $request->csrfParam,
            'CSRF parameter should default to XSRF-TOKEN.',
        );
    }

    public function testCsrfValidationFailsWithInvalidHeader(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request->getCsrfToken();
        $request->headers->set('X-XSRF-TOKEN', 'invalid-token');

        self::assertFalse(
            $request->validateCsrfToken(),
            "CSRF validation should fail with an invalid 'header' token.",
        );
    }

    public function testCsrfValidationFailsWithoutHeader(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request->getCsrfToken();

        self::assertFalse(
            $request->validateCsrfToken(),
            "CSRF validation should fail when no token 'header' is present.",
        );
    }

    public function testCsrfValidationPassesWithValidSignedHeader(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // getCsrfToken() generates a raw token and stores it in the response cookie.
        $request->getCsrfToken();

        $cookie = Yii::$app->getResponse()->getCookies()->get('XSRF-TOKEN');

        self::assertNotNull(
            $cookie,
            "Response should contain the XSRF-TOKEN 'cookie'.",
        );

        $signed = Yii::$app->getSecurity()->hashData(
            serialize(['XSRF-TOKEN', $cookie->value]),
            $request->cookieValidationKey,
        );

        $request->headers->set('X-XSRF-TOKEN', $signed);

        self::assertTrue(
            $request->validateCsrfToken(),
            "CSRF validation should pass with a valid signed 'header' token.",
        );
    }

    public function testGetCsrfTokenFromHeaderCallsUnserializeWithAllowedClassesFalse(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $signed = Yii::$app->getSecurity()->hashData(
            serialize(['XSRF-TOKEN', 'token-value']),
            $request->cookieValidationKey,
        );

        MockerFunctions::reset();

        $request->headers->set('X-XSRF-TOKEN', $signed);
        $request->getCsrfTokenFromHeader();

        $calls = MockerFunctions::getUnserializeCalls();

        self::assertCount(
            1,
            $calls,
            'Should call unserialize exactly once.',
        );
        self::assertArrayHasKey(
            'allowed_classes',
            $calls[0]['options'],
            "unserialize should be called with an options array containing the 'allowed_classes' key.",
        );
        self::assertFalse(
            $calls[0]['options']['allowed_classes'],
            "Option 'allowed_classes' should be 'false' to prevent object instantiation.",
        );
    }

    public function testGetCsrfTokenFromHeaderExtractsAndMasksToken(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $rawToken = 'raw-csrf-token-value-32chars-ok!';

        $signed = Yii::$app->getSecurity()->hashData(
            serialize(['XSRF-TOKEN', $rawToken]),
            $request->cookieValidationKey,
        );

        $request->headers->set('X-XSRF-TOKEN', $signed);
        $result = $request->getCsrfTokenFromHeader();

        self::assertNotNull(
            $result,
            'Should return a non-null token.',
        );
        self::assertNotSame(
            $rawToken,
            $result,
            'Should return a masked token, not the raw token.',
        );
        self::assertSame(
            $rawToken,
            Yii::$app->getSecurity()->unmaskToken($result),
            'Unmasking the returned token should recover the original raw token.',
        );
    }

    public function testGetCsrfTokenFromHeaderRejectsSerializedObjects(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $signed = Yii::$app->getSecurity()->hashData(
            serialize(['XSRF-TOKEN', new \stdClass()]),
            $request->cookieValidationKey,
        );

        $request->headers->set('X-XSRF-TOKEN', $signed);

        self::assertNull(
            $request->getCsrfTokenFromHeader(),
            "Should return 'null' when payload contains serialized objects.",
        );
    }

    public function testGetCsrfTokenFromHeaderReturnsNullForInvalidHmac(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $request->headers->set('X-XSRF-TOKEN', 'tampered-garbage-value');

        self::assertNull(
            $request->getCsrfTokenFromHeader(),
            "Should return 'null' when HMAC validation fails.",
        );
    }

    public function testGetCsrfTokenFromHeaderReturnsNullForInvalidPayload(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $signed = Yii::$app->getSecurity()->hashData(
            serialize(['WRONG-PARAM', 'some-token']),
            $request->cookieValidationKey,
        );

        $request->headers->set('X-XSRF-TOKEN', $signed);

        self::assertNull(
            $request->getCsrfTokenFromHeader(),
            "Should return 'null' when the deserialized payload has a mismatched param name.",
        );
    }

    public function testGetCsrfTokenFromHeaderReturnsNullForNonArrayPayload(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $signed = Yii::$app->getSecurity()->hashData(
            serialize('not-an-array'),
            $request->cookieValidationKey,
        );

        $request->headers->set('X-XSRF-TOKEN', $signed);

        self::assertNull(
            $request->getCsrfTokenFromHeader(),
            "Should return 'null' when the deserialized payload is not an array.",
        );
    }

    public function testGetCsrfTokenFromHeaderReturnsNullForNonStringToken(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $signed = Yii::$app->getSecurity()->hashData(
            serialize(['XSRF-TOKEN', 12345]),
            $request->cookieValidationKey,
        );

        $request->headers->set('X-XSRF-TOKEN', $signed);

        self::assertNull(
            $request->getCsrfTokenFromHeader(),
            "Should return 'null' when the token value is not a string.",
        );
    }

    public function testGetCsrfTokenFromHeaderReturnsNullWhenHeaderAbsent(): void
    {
        $this->mockWebApplicationWithInertiaRequest();

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);
        self::assertNull(
            $request->getCsrfTokenFromHeader(),
            "Should return 'null' when header is absent.",
        );
    }

    public function testGetCsrfTokenFromHeaderReturnsRawTokenWhenCookieValidationDisabled(): void
    {
        $this->mockWebApplicationWithInertiaRequest([
            'components' => [
                'request' => [
                    'enableCookieValidation' => false,
                ],
            ],
        ]);

        $request = Yii::$app->getRequest();

        self::assertInstanceOf(Request::class, $request);

        $request->headers->set('X-XSRF-TOKEN', 'raw-token-value');

        self::assertSame(
            'raw-token-value',
            $request->getCsrfTokenFromHeader(),
            "Should return the raw 'header' value when 'cookie' validation is disabled.",
        );
    }

    /**
     * @phpstan-param array<string, mixed> $override
     */
    private function mockWebApplicationWithInertiaRequest(array $override = []): void
    {
        $this->destroyApplication();

        /** @var array<string, mixed> $config */
        $config = ArrayHelper::merge(
            [
                'components' => [
                    'request' => [
                        'class' => Request::class,
                    ],
                ],
            ],
            $override,
        );

        $this->mockWebApplication($config);
    }
}
