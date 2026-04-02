# Usage Examples

## Shared props

```php
use yii\inertia\Inertia;

Inertia::share([
    'auth.user' => static fn(): ?array => Yii::$app->user->isGuest
        ? null
        : ['id' => Yii::$app->user->getId()],
    'csrf' => static fn(): array => [
        'param' => Yii::$app->request->csrfParam,
        'token' => Yii::$app->request->getCsrfToken(),
        'header' => Yii::$app->request->csrfHeader,
    ],
]);
```

## Rendering a page

```php
return Inertia::render('Users/Index', [
    'users' => $dataProvider->getModels(),
    'filters' => Yii::$app->request->getQueryParams(),
]);
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
