# Development Notes

## Scope

This package defines the server-side Inertia contract for Yii2 applications.

It intentionally does not include:

- Vue bootstrapping.
- React bootstrapping.
- Svelte bootstrapping.
- Server-side rendering.
- A direct replacement for legacy jQuery widgets.

## Planned package family

The intended package family is:

- `yii2-framework/inertia`
- `yii2-framework/inertia-vue`
- `yii2-framework/inertia-react`
- `yii2-framework/inertia-svelte`

## Protocol choice

The implementation follows the current Inertia v3 direction for initial page transport by embedding the initial page
JSON in a `<script type="application/json">` element inside the root application container.

> **Note:** Current `@inertiajs/*` client packages default to reading the `data-page` attribute. To use the
> script-element transport you must enable the flag on the client side:
>
> ```js
> import { defaults } from '@inertiajs/vue3' // or react, svelte
>
> defaults.future.useScriptElementForInitialPage = true
> ```
>
> Without this flag the page will not hydrate.
