# Installation guide

## System requirements

- [PHP](https://www.php.net/downloads) `8.2` or higher.
- [Composer](https://getcomposer.org/download/) for dependency management.

## Install the package

Add the package with Composer:

```bash
composer require yii2-framework/inertia:^0.1
```

## Register the bootstrap integration

Enable the Inertia integration in your web configuration:

```php
// config/web.php
return [
    'bootstrap' => [\yii\inertia\Bootstrap::class],
];
```

This bootstrap registers the `inertia` application component, sets the `@inertia` alias, and normalizes Yii Ajax
redirects so they follow the Inertia protocol.

## Client adapter

This package does not include a JavaScript client adapter. Install one of the following adapter packages in your
application:

- `yii2-framework/inertia-vue` (planned)
- `yii2-framework/inertia-react` (planned)
- `yii2-framework/inertia-svelte` (planned)

## When not to install this package

Do not install `yii2-framework/inertia` for applications that rely exclusively on traditional Yii2 server-rendered views
and jQuery widgets. In that scenario, use `yii2-framework/jquery` instead.

## Next steps

- ⚙️ [Configuration Reference](configuration.md)
- 💡 [Usage Examples](examples.md)
- 🧪 [Testing Guide](testing.md)
- 🛠️ [Development Guide](development.md)
