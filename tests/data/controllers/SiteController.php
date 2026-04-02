<?php

declare(strict_types=1);

namespace yii\inertia\tests\data\controllers;

use yii\inertia\web\Controller;
use yii\web\Response;

/**
 * Test controller for {@see \yii\inertia\web\Controller} integration scenarios.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class SiteController extends Controller
{
    public function actionExternal(): Response
    {
        return $this->location('/login');
    }

    public function actionIndex(): Response
    {
        return $this->inertia(
            'Dashboard',
            [
                'stats' => [
                    'visits' => 42,
                ],
            ],
        );
    }
}
