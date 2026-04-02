<!-- markdownlint-disable MD041 -->
<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_dark.svg">
        <source media="(prefers-color-scheme: light)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg">
        <img src="https://www.yiiframework.com/image/design/logo/yii3_full_for_dark.svg" alt="Yii Framework" width="80%">
    </picture>
    <h1 align="center">Inertia</h1>
    <br>
</p>
<!-- markdownlint-enable MD041 -->

<p align="center">
    <a href="https://github.com/yii2-framework/inertia/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-framework/inertia/build.yml?style=for-the-badge&logo=github&label=PHPUnit" alt="PHPUnit">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/yii2-framework/inertia/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii2-framework%2Finertia%2Fmain" alt="Mutation Testing">
    </a>
    <a href="https://github.com/yii2-framework/inertia/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-framework/inertia/static.yml?style=for-the-badge&logo=github&label=PHPStan" alt="PHPStan">
    </a>
</p>

<p align="center">
    <strong>Inertia.js server-side integration layer for <a href="https://github.com/yii2-framework/core">yii2-framework/core</a></strong><br>
    <em>Server-driven pages, shared props, redirects, and asset version handling without jQuery</em>
</p>

## Features

<picture>
    <source media="(min-width: 768px)" srcset="./docs/svgs/features.svg">
    <img src="./docs/svgs/features-mobile.svg" alt="Feature Overview" style="width: 100%;">
</picture>

## Overview

`yii2-framework/inertia` is the server-side base package for building modern Inertia-driven pages on top of Yii2.
It does not ship a client adapter. Instead, it defines the server contract that future packages such as
`yii2-framework/inertia-vue`, `yii2-framework/inertia-react`, and `yii2-framework/inertia-svelte` can reuse.

## Installation

```bash
composer require yii2-framework/inertia
```

Register the bootstrap class in your application configuration:

```php
return [
    'bootstrap' => [\yii\inertia\Bootstrap::class],
];
```

## Quick start

Render a page directly from a controller action:

```php
use yii\inertia\Inertia;
use yii\web\Controller;
use yii\web\Response;

final class SiteController extends Controller
{
    public function actionIndex(): Response
    {
        return Inertia::render(
            'Dashboard',
            ['stats' => ['visits' => 42]],
        );
    }
}
```

Or extend the convenience controller:

```php
use yii\inertia\web\Controller;
use yii\web\Response;

final class SiteController extends Controller
{
    public function actionIndex(): Response
    {
        return $this->inertia(
            'Dashboard',
            [
                'stats' => ['visits' => 42],
            ]
        );
    }
}
```

## Configuration example

```php
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
            'shared' => ['app.name' => static fn(): string => Yii::$app->name],
        ],
    ],
];
```

## Validation and flash messages

This package maps the session flash key `errors` to `props.errors` and exposes all remaining flashes at the top-level
`flash` page key. A typical validation redirect looks like this:

```php
if (!$model->validate()) {
    Yii::$app->session->setFlash('errors', $model->getErrors());

    return $this->redirect(['create']);
}

Yii::$app->session->setFlash('success', 'Record created.');

return $this->redirect(['view', 'id' => $model->id]);
```

## Package boundaries

This repository intentionally does not include Vue, React, or Svelte bootstrapping. Those concerns belong in separate
client adapter packages built on top of the server contract defined here.

## Documentation

For detailed configuration options and advanced usage.

- 📚 [Installation Guide](docs/installation.md)
- ⚙️ [Configuration Reference](docs/configuration.md)
- 💡 [Usage Examples](docs/examples.md)
- 🧪 [Testing Guide](docs/testing.md)

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.2-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.2/en.php)
[![Latest Stable Version](https://img.shields.io/packagist/v/yii2-framework/inertia.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Stable)](https://packagist.org/packages/yii2-framework/inertia)
[![Total Downloads](https://img.shields.io/packagist/dt/yii2-framework/inertia.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/yii2-framework/inertia)

## Quality code

[![Codecov](https://img.shields.io/codecov/c/github/yii2-framework/inertia.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/github/yii2-framework/inertia)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/yii2-framework/inertia/actions/workflows/static.yml)
[![Super-Linter](https://img.shields.io/github/actions/workflow/status/yii2-framework/inertia/linter.yml?style=for-the-badge&label=Super-Linter&logo=github)](https://github.com/yii2-framework/inertia/actions/workflows/linter.yml)
[![StyleCI](https://img.shields.io/badge/StyleCI-Passed-44CC11.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.styleci.io/repos/1196150046?branch=main)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)
