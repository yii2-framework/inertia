<?php

declare(strict_types=1);

namespace yii\inertia\tests;

use Yii;
use yii\helpers\Json;
use yii\inertia\Page;
use yii\inertia\tests\support\ApplicationFactory;
use yii\web\Response;

/**
 * Base test case for yii2-framework/inertia tests.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private const PAGE_JSON_PATTERN = '/<script type="application\/json">(.*?)<\/script>/s';

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        Yii::getLogger()->flush();
    }

    /**
     * Destroys the current application.
     */
    protected function destroyApplication(): void
    {
        ApplicationFactory::destroy();
    }

    /**
     * Extracts the page object from an HTML or JSON response.
     *
     * @return array Page payload containing 'component', 'props', 'url', 'version', and optional v3 metadata keys.
     *
     * @phpstan-return array{
     *   component: string,
     *   props: array<string, mixed>,
     *   url: string,
     *   version: int|string,
     *   flash?: array<string, mixed>,
     *   clearHistory?: bool,
     *   encryptHistory?: bool,
     *   deferredProps?: array<string, list<string>>,
     *   mergeProps?: list<string>,
     *   prependProps?: list<string>,
     *   deepMergeProps?: list<string>,
     *   matchPropsOn?: array<string, string>,
     *   scrollProps?: array<string, array<string, mixed>>,
     *   onceProps?: array<string, array<string, mixed>>,
     * }
     */
    protected function extractPage(Response $response): array
    {
        if ($response->data instanceof Page) {
            return $response->data->jsonSerialize();
        }

        $content = (string) $response->content;

        self::assertMatchesRegularExpression(
            self::PAGE_JSON_PATTERN,
            $content,
            'HTML response should contain an inline JSON script with the page payload.',
        );

        $result = preg_match(self::PAGE_JSON_PATTERN, $content, $matches);

        self::assertSame(
            1,
            $result,
            'Regex should match exactly one JSON script block.',
        );

        $decoded = Json::decode($matches[1]);

        self::assertIsArray(
            $decoded,
            'Decoded JSON page payload should be an array.',
        );

        /**
         * @phpstan-var array{
         *   component: string,
         *   props: array<string, mixed>,
         *   url: string,
         *   version: int|string,
         *   flash?: array<string, mixed>,
         *   clearHistory?: bool,
         *   encryptHistory?: bool,
         *   deferredProps?: array<string, list<string>>,
         *   mergeProps?: list<string>,
         *   prependProps?: list<string>,
         *   deepMergeProps?: list<string>,
         *   matchPropsOn?: array<string, string>,
         *   scrollProps?: array<string, array<string, mixed>>,
         *   onceProps?: array<string, array<string, mixed>>,
         * } $decoded
         */
        return $decoded;
    }

    /**
     * Populates `Yii::$app` with a new web application configured for Inertia tests.
     *
     * @param array $config Additional configuration to merge with the default application config.
     *
     * @phpstan-param array<string, mixed> $config
     */
    protected function mockWebApplication(array $config = []): void
    {
        ApplicationFactory::web($config);
    }

    /**
     * Populates `Yii::$app` with a web application that has no session component.
     *
     * @param array $config Additional configuration to merge with the default application config.
     *
     * @phpstan-param array<string, mixed> $config
     */
    protected function mockWebApplicationWithoutSession(array $config = []): void
    {
        ApplicationFactory::webWithoutSession($config);
    }

    /**
     * Marks the current request as an Inertia request.
     *
     * @param string $method HTTP method to set for the request (default: `GET`).
     */
    protected function prepareInertiaRequest(string $method = 'GET'): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        Yii::$app->getRequest()->getHeaders()->set('X-Inertia', 'true');
        Yii::$app->getRequest()->getHeaders()->set('X-Requested-With', 'XMLHttpRequest');
    }

    /**
     * Sets the current absolute request URL for tests.
     *
     * @param string $url URL to set (for example, `/users/1`).
     */
    protected function setAbsoluteUrl(string $url): void
    {
        Yii::$app->getRequest()->setHostInfo('https://example.test');
        Yii::$app->getRequest()->setUrl($url);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->destroyApplication();
    }
}
