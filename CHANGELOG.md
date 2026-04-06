# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## 0.1.0 Under development

- feat: initial `yii2-framework/inertia` package structure.
- docs: standardize documentation style, feature SVGs, cross-navigation, and `testing.md` to match `yii2-framework/jquery` conventions.
- chore: Raise code coverage to `100%` and ensure all tests pass successfully.
- fix: update `README.md` to reference correct Yii2 framework repository.
- docs: update installation instructions and correct Yii2 capitalization in `README.md`.
- fix: update `composer.json` to require the correct Yii2 framework package.
- refactor: move `flash` and `errors` injection into `resolveProps()` and fix `consumeFlashes()` session initialization so flash data reaches Vue components reliably.
- feat: add Inertia `v3` protocol support with prop wrappers, new page object fields, and partial-reload header handling.
- feat: add `web\Request` with `cookie-to-header` CSRF protection compatible with Inertia's built-in HTTP client.
- fix: mask raw token in `web\Request::getCsrfTokenFromHeader()` so `validateCsrfToken()` comparison succeeds.
