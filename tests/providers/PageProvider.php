<?php

declare(strict_types=1);

namespace yii\inertia\tests\providers;

/**
 * Data provider for {@see \yii\inertia\tests\PageTest} test cases.
 *
 * Provides representative input/output pairs for page payload serialization.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class PageProvider
{
    /**
     * @phpstan-return array<string, array{
     *   array{
     *     component: string,
     *     props: array<string, mixed>,
     *     url: string,
     *     version: int|string,
     *     flash: array<string, mixed>,
     *     clearHistory: bool,
     *     encryptHistory: bool,
     *   },
     *   array<string, mixed>,
     * }>
     */
    public static function jsonSerialize(): array
    {
        return [
            'clearHistory false omitted from output' => [
                [
                    'component' => 'Home',
                    'props' => [],
                    'url' => '/',
                    'version' => '',
                    'flash' => [],
                    'clearHistory' => false,
                    'encryptHistory' => false,
                ],
                [
                    'component' => 'Home',
                    'props' => [],
                    'url' => '/',
                    'version' => '',
                ],
            ],
            'minimal payload' => [
                [
                    'component' => 'Home',
                    'props' => [],
                    'url' => '/',
                    'version' => '',
                    'flash' => [],
                    'clearHistory' => false,
                    'encryptHistory' => false,
                ],
                [
                    'component' => 'Home',
                    'props' => [],
                    'url' => '/',
                    'version' => '',
                ],
            ],
            'with all optional fields' => [
                [
                    'component' => 'Admin',
                    'props' => ['role' => 'admin'],
                    'url' => '/admin',
                    'version' => 42,
                    'flash' => ['info' => 'Welcome.'],
                    'clearHistory' => true,
                    'encryptHistory' => true,
                ],
                [
                    'component' => 'Admin',
                    'props' => ['role' => 'admin'],
                    'url' => '/admin',
                    'version' => 42,
                    'flash' => ['info' => 'Welcome.'],
                    'clearHistory' => true,
                    'encryptHistory' => true,
                ],
            ],
            'with clearHistory true' => [
                [
                    'component' => 'Login',
                    'props' => [],
                    'url' => '/login',
                    'version' => '',
                    'flash' => [],
                    'clearHistory' => true,
                    'encryptHistory' => false,
                ],
                [
                    'component' => 'Login',
                    'props' => [],
                    'url' => '/login',
                    'version' => '',
                    'clearHistory' => true,
                ],
            ],
            'with encryptHistory true' => [
                [
                    'component' => 'Settings',
                    'props' => [],
                    'url' => '/settings',
                    'version' => '',
                    'flash' => [],
                    'clearHistory' => false,
                    'encryptHistory' => true,
                ],
                [
                    'component' => 'Settings',
                    'props' => [],
                    'url' => '/settings',
                    'version' => '',
                    'encryptHistory' => true,
                ],
            ],
            'with flash data' => [
                [
                    'component' => 'Profile',
                    'props' => [],
                    'url' => '/profile',
                    'version' => '',
                    'flash' => ['success' => 'Saved.'],
                    'clearHistory' => false,
                    'encryptHistory' => false,
                ],
                [
                    'component' => 'Profile',
                    'props' => [],
                    'url' => '/profile',
                    'version' => '',
                    'flash' => ['success' => 'Saved.'],
                ],
            ],
            'with props and version' => [
                [
                    'component' => 'Dashboard',
                    'props' => ['user' => ['id' => 1]],
                    'url' => '/dashboard',
                    'version' => 'build-1',
                    'flash' => [],
                    'clearHistory' => false,
                    'encryptHistory' => false,
                ],
                [
                    'component' => 'Dashboard',
                    'props' => ['user' => ['id' => 1]],
                    'url' => '/dashboard',
                    'version' => 'build-1',
                ],
            ],
        ];
    }
}
