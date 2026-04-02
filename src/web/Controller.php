<?php

declare(strict_types=1);

namespace yii\inertia\web;

use yii\inertia\Inertia;
use yii\web\Response;

/**
 * Base controller for Inertia-driven pages.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
abstract class Controller extends \yii\web\Controller
{
    /**
     * Renders an Inertia page.
     *
     * @param string $component Inertia component name.
     * @param array $props Props to pass to the component.
     * @param array $viewData Additional view data.
     *
     * @return Response Response containing the rendered Inertia page.
     *
     * @phpstan-param array<string, mixed> $props
     * @phpstan-param array<string, mixed> $viewData
     */
    protected function inertia(string $component, array $props = [], array $viewData = []): Response
    {
        return Inertia::render($component, $props, $viewData);
    }

    /**
     * Returns an Inertia location response.
     *
     * @param array|string $url URL to redirect to, either as a string or an array that can be processed by `Url::to()`.
     *
     * @return Response Response containing the Inertia location header.
     *
     * @phpstan-param array<string, mixed>|string $url
     */
    protected function location(array|string $url): Response
    {
        return Inertia::location($url);
    }
}
