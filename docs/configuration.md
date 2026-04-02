# Configuration Reference

## Application component

The package exposes a configurable `inertia` application component of type `yii\inertia\Manager`.

```php
use yii\inertia\Manager;

return [
    'components' => [
        'inertia' => [
            'class' => Manager::class,
            'id' => 'app',
            'rootView' => '@app/views/layouts/inertia.php',
            'version' => static fn(): int => filemtime(dirname(__DIR__) . '/public/build/manifest.json'),
            'shared' => [
                'auth.user' => static fn() => Yii::$app->user->identity,
                'app.name' => static fn(): string => Yii::$app->name,
            ],
        ],
    ],
];
```

## Properties

### `id`

DOM id used by the default root view. Defaults to `app`.

### `rootView`

View file used for the first full HTML response. Defaults to `@inertia/views/app.php`.

### `version`

Current asset version. It can be a string, integer, or closure. Inertia requests compare it against the
`X-Inertia-Version` request header.

### `shared`

Associative array of shared props. Dot notation is supported for nested props.

### `errorFlashKey`

Session flash key that will be exposed as `props.errors`. Defaults to `errors`.
