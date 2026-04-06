<?php

declare(strict_types=1);

namespace yii\inertia;

use Yii;
use yii\base\{BootstrapInterface, Event};
use yii\web\Response;

use function in_array;

/**
 * Bootstraps the Inertia integration layer.
 *
 * Registers the `inertia` application component when it is missing, exposes the `@inertia` alias for the package source
 * directory, and normalizes Yii AJAX redirects so they follow the Inertia protocol.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class Bootstrap implements BootstrapInterface
{
    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app): void
    {
        Yii::setAlias('@inertia', __DIR__);

        if (!$app->has('inertia')) {
            $app->set('inertia', ['class' => Manager::class]);
        }

        $app->getResponse()->on(
            Response::EVENT_BEFORE_SEND,
            static function (Event $event): void {
                $response = $event->sender;

                if (!$response instanceof Response) {
                    return;
                }

                $manager = Yii::$app->get('inertia');

                if (!$manager instanceof Manager || !$manager->isInertiaRequest()) {
                    return;
                }

                $vary = $response->getHeaders()->get('Vary');

                if ($vary === null || trim($vary) === '') {
                    $response->getHeaders()->set('Vary', 'X-Inertia');
                } else {
                    $tokens = array_map(
                        static fn(string $token): string => strtolower(trim($token)),
                        explode(',', $vary),
                    );

                    if (!in_array('x-inertia', $tokens, true)) {
                        $response->getHeaders()->set('Vary', $vary . ', X-Inertia');
                    }
                }

                $redirect = $response->getHeaders()->get('X-Redirect');

                if ($redirect !== null) {
                    $response->getHeaders()->remove('X-Redirect');
                    $response->getHeaders()->set('Location', $redirect);
                }

                $request = Yii::$app->getRequest();

                if (
                    in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)
                    && in_array($response->statusCode, [301, 302], true)
                ) {
                    $response->setStatusCode(303);
                }
            },
        );
    }
}
