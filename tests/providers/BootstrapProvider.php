<?php

declare(strict_types=1);

namespace yii\inertia\tests\providers;

/**
 * Data provider for {@see \yii\inertia\tests\BootstrapTest} test cases.
 *
 * Provides representative input/output pairs for Inertia redirect normalization.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class BootstrapProvider
{
    /**
     * Cases where the redirect should be normalized to 303.
     *
     * @phpstan-return array<string, array{string, int}>
     */
    public static function redirectNormalizedTo303(): array
    {
        return [
            'DELETE with 301' => ['DELETE', 301],
            'DELETE with 302' => ['DELETE', 302],
            'PATCH with 301' => ['PATCH', 301],
            'PATCH with 302' => ['PATCH', 302],
            'PUT with 301' => ['PUT', 301],
            'PUT with 302' => ['PUT', 302],
        ];
    }

    /**
     * Cases where the redirect should NOT be normalized to 303.
     *
     * @phpstan-return array<string, array{string, int}>
     */
    public static function redirectNotNormalizedTo303(): array
    {
        return [
            'DELETE with 404' => ['DELETE', 404],
            'GET with 301' => ['GET', 301],
            'GET with 302' => ['GET', 302],
            'POST with 301' => ['POST', 301],
            'POST with 302' => ['POST', 302],
            'PUT with 200' => ['PUT', 200],
            'PUT with 303' => ['PUT', 303],
        ];
    }
}
