# Testing Guide

## Standard workflow

After installing development dependencies, run the package test suite with:

```bash
composer test
```

## Monorepo-style local verification

If the package is being developed next to `yii2-framework/core` and local dependencies are not yet installed in this
repository, the included bootstrap file can reuse the sibling Core autoloader. In that setup you can run:

```bash
../core/vendor/bin/phpunit -c phpunit.xml.dist
```
