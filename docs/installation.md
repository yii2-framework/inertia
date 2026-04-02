# Installation Guide

## Composer package

Install the server package:

```bash
composer require yii2-framework/inertia
```

## Bootstrap registration

Register the package bootstrap class in your application configuration:

```php
return [
    'bootstrap' => [\yii\inertia\Bootstrap::class],
];
```

The bootstrap class registers the `inertia` application component when it is not already configured.

## Client adapter

This package does not include a JavaScript client adapter. Install a separate adapter package, such as a future
`yii2-framework/inertia-vue`, in the application that consumes this package.
