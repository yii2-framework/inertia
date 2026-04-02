# Development

This document describes development workflows and maintenance tasks for the project.

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
> import { defaults } from "@inertiajs/vue3"; // or react, svelte
>
> defaults.future.useScriptElementForInitialPage = true;
> ```
>
> Without this flag the page will not hydrate.

## Updated files

This command updates the following configuration files:

| File               | Purpose                                      |
| ------------------ | -------------------------------------------- |
| `.editorconfig`    | Editor settings and code style configuration |
| `.gitattributes`   | Git attributes and file handling rules       |
| `.gitignore`       | Git ignore patterns and exclusions           |
| `infection.json5`  | Infection mutation testing configuration     |
| `phpstan.neon`     | PHPStan static analysis configuration        |
| `phpunit.xml.dist` | PHPUnit test configuration                   |

## When to run

Run this command in the following scenarios:

- **Periodic updates** - Monthly or quarterly to benefit from template improvements.
- **After template updates** - When the template repository has new configuration improvements.
- **Before major releases** - Ensure your project uses the latest best practices.
- **When issues occur** - If configuration files become outdated or incompatible.

## Important notes

- This command overwrites existing configuration files with the latest versions from the template.
- Ensure you have committed any custom configuration changes before running this command.
- Review the updated files after syncing to ensure they work with your specific project needs.
- Some projects may require customizations after syncing configuration files.

## Next steps

- 📚 [Installation Guide](installation.md)
- ⚙️ [Configuration Reference](configuration.md)
- 💡 [Usage Examples](examples.md)
- 🧪 [Testing Guide](testing.md)
