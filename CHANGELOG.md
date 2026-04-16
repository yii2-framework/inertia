# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## 0.1.1 Under development

## 0.1.0 April 16, 2026

- feat: initial `yii2-extensions/inertia` package structure.
- docs: standardize documentation style, feature SVGs, cross-navigation, and `testing.md` to match `yii2-extensions/jquery` conventions.
- chore: Raise code coverage to `100%` and ensure all tests pass successfully.
- fix: update `README.md` to reference correct Yii2 framework repository.
- docs: update installation instructions and correct Yii2 capitalization in `README.md`.
- fix: update `composer.json` to require the correct Yii2 framework package.
- refactor: move `flash` and `errors` injection into `resolveProps()` and fix `consumeFlashes()` session initialization so flash data reaches Vue components reliably.
- feat: add Inertia `v3` protocol support with prop wrappers, new page object fields, and partial-reload header handling.
- feat: add `web\Request` with `cookie-to-header` CSRF protection compatible with Inertia's built-in HTTP client.
- fix: mask raw token in `web\Request::getCsrfTokenFromHeader()` so `validateCsrfToken()` comparison succeeds.
- docs: document `web\Request` cookie-to-header CSRF protection in `README.md`, `configuration.md`, and `examples.md`.
- feat: add `Vite` component for manifest parsing, dev-server tag rendering, and module preload; framework adapters inject preamble scripts via `preambleProvider` closure.
- chore: rename package and documentation references from `yii2-framework` to `yii2-extensions` after organization move.
- docs: update PHP version requirement to `8.3` in installation docs and align related `README.md` links.
- chore: update PHP version to `8.3` in `.styleci.yml`; enhance SVG features documentation for clarity and consistency and adjust logo source in `README.md`.
- docs: document the `\yii\inertia\Vite` dev server / HMR behavior in `README.md`, with pointers to the React and Vue adapter packages for framework-specific setup.
