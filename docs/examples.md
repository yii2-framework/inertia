# Usage examples

## Shared props

```php
use yii\inertia\Inertia;

Inertia::share(
    [
        'auth.user' => static fn(): ?array => Yii::$app->user->isGuest
            ? null
            : ['id' => Yii::$app->user->getId()],
        'csrf' => static fn(): array => [
            'param' => Yii::$app->request->csrfParam,
            'token' => Yii::$app->request->getCsrfToken(),
            'header' => Yii::$app->request->csrfHeader,
        ],
    ],
);
```

## Rendering a page

```php
return Inertia::render(
    'Users/Index',
    [
        'users' => $dataProvider->getModels(),
        'filters' => Yii::$app->request->getQueryParams(),
    ],
);
```

## Validation redirect

```php
if (!$model->validate()) {
    Yii::$app->session->setFlash('errors', $model->getErrors());
    return $this->redirect(['create']);
}

Yii::$app->session->setFlash('success', 'User saved.');

return $this->redirect(['view', 'id' => $model->id]);
```

## External redirect for Inertia requests

```php
return Inertia::location('https://example.com/account/login');
```

## Deferred props

Props excluded from the initial response and loaded asynchronously after the page renders. Props sharing the same
group are fetched together.

```php
return Inertia::render(
    'Dashboard',
    [
        'stats' => $stats,
        'users' => Inertia::defer(fn () => User::find()->all()),
        'roles' => Inertia::defer(fn () => Role::find()->all(), 'sidebar'),
    ],
);
```

## Optional props

Props only resolved when the client explicitly requests them via a partial reload.

```php
return Inertia::render(
    'Users/Show',
    [
        'user' => $user->toArray(),
        'activity' => Inertia::optional(fn () => $user->getActivityLog()),
    ]
);
```

## Always props

Props included in every response, even during partial reloads that do not list them.

```php
return Inertia::render(
    'Dashboard',
    [
        'auth' => Inertia::always(fn () => ['user' => Yii::$app->user->identity]),
        'stats' => $stats,
    ],
);
```

## Merge props

Props that merge with existing client-side data during partial reloads instead of replacing it.

```php
return Inertia::render(
    'Users/Index',
    [
        'users' => Inertia::merge($paginatedUsers)->append('data', 'id'),
        'logs' => Inertia::deepMerge($nestedLogs),
        'messages' => Inertia::merge($messages)->prepend('data'),
    ],
);
```

## Once props

Props resolved once and cached on the client-side with an optional TTL.

```php
return Inertia::render(
    'Settings',
    [
        'countries' => Inertia::once(fn () => Country::find()->all())
            ->as('countries-v1')
            ->until(3600),
    ],
);
```

## Next steps

- 📚 [Installation Guide](installation.md)
- ⚙️ [Configuration Reference](configuration.md)
- 🧪 [Testing Guide](testing.md)
