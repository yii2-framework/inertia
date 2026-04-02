# Configuration reference

## Overview

`yii2-framework/inertia` provides a server-side Inertia response manager registered as the `inertia` application
component. The bootstrap class registers it automatically when missing.

## Basic configuration

Enable the package through the application bootstrap and configure the component:

```php
// config/web.php
use yii\inertia\Manager;

return [
    'bootstrap' => [\yii\inertia\Bootstrap::class],
    'components' => [
        'inertia' => [
            'class' => Manager::class,
            'id' => 'app',
            'rootView' => '@app/views/layouts/inertia.php',
            'version' => static function (): string {
                $path = dirname(__DIR__) . '/public/build/manifest.json';

                return is_file($path) ? (string) filemtime($path) : '';
            },
            'shared' => [
                'auth.user' => static fn(): ?array => Yii::$app->user->isGuest
                    ? null
                    : ['id' => Yii::$app->user->getId()],
                'app.name' => static fn(): string => Yii::$app->name,
            ],
        ],
    ],
];
```

## Properties

### `id`

DOM ID used by the default root view. Defaults to `app`.

### `rootView`

View file used for the first full HTML response. Defaults to `@inertia/views/app.php`.

### `version`

Current asset version. It can be a string, integer, or closure. Inertia requests compare it against the
`X-Inertia-Version` request header.

### `shared`

Associative array of shared props. Dot notation is supported for nested props.

### `errorFlashKey`

Session flash key that will be exposed as `props.errors`. Defaults to `errors`.

## Next steps

- 📚 [Installation Guide](installation.md)
- 💡 [Usage Examples](examples.md)
- 🧪 [Testing Guide](testing.md)
