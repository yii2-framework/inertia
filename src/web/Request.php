<?php

declare(strict_types=1);

namespace yii\inertia\web;

use Yii;

use function is_array;

/**
 * Configures CSRF protection for Inertia applications using the cookie-to-header pattern.
 *
 * Sets a non-httpOnly `XSRF-TOKEN` cookie that Inertia's built-in HTTP client reads automatically and sends back as the
 * `X-XSRF-TOKEN` header on every request. When cookie validation is enabled, the header value is HMAC-signed; this
 * class transparently unsigns and extracts the masked token before Yii's CSRF comparison runs.
 *
 * Usage example:
 *
 * ```php
 * // config/web.php
 * return [
 *     'components' => [
 *         'request' => [
 *             'class' => \yii\inertia\web\Request::class,
 *             'cookieValidationKey' => 'your-secret-key',
 *             'parsers' => ['application/json' => \yii\web\JsonParser::class],
 *         ],
 *     ],
 * ];
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.2
 */
class Request extends \yii\web\Request
{
    /**
     * Cookie options. `httpOnly` is `false` so JavaScript can read the CSRF token.
     *
     * @phpstan-var mixed[]
     */
    public $csrfCookie = ['httpOnly' => false];
    /**
     * Header checked for the CSRF token sent by Inertia's HTTP client.
     */
    public $csrfHeader = 'X-XSRF-TOKEN';
    /**
     * Cookie name and form parameter name for the CSRF token.
     */
    public $csrfParam = 'XSRF-TOKEN';

    /**
     * Returns the CSRF token sent via the `X-XSRF-TOKEN` header.
     *
     * When cookie validation is enabled, the raw header value is HMAC-signed. This method validates the signature,
     * deserializes the payload, and extracts the masked token expected by Yii's CSRF comparison.
     *
     * Usage example:
     *
     * ```php
     * $token = Yii::$app->request->getCsrfTokenFromHeader();
     * ```
     *
     * @return string|null Masked CSRF token, or `null` when the header is absent or invalid.
     */
    public function getCsrfTokenFromHeader(): string|null
    {
        $token = $this->headers->get($this->csrfHeader);

        if ($token === null) {
            return null;
        }

        if (!$this->enableCookieValidation) {
            return $token;
        }

        $data = Yii::$app->getSecurity()->validateData($token, $this->cookieValidationKey);

        if ($data === false) {
            return null;
        }

        $data = @unserialize($data, ['allowed_classes' => false]);

        if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $this->csrfParam && is_string($data[1])) {
            return $data[1];
        }

        return null;
    }
}
