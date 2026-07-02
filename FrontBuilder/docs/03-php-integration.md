# PHP Integration

## Overview

The build system generates PHP classes that provide type-safe access to compiled assets. This eliminates magic strings and enables IDE autocompletion in PHP code.

---

## Generated PHP Classes

### Purpose

Instead of hardcoding asset paths in Twig templates:

```twig
{# Bad - magic string, no IDE support #}
<script src="/assets/application/gen/js/dashboard.main.abc123.js"></script>
```

Use generated PHP classes:

```twig
{# Good - type-safe, IDE autocomplete #}
<script src="{{ DashboardJsGen::main() }}"></script>
```

### Class Structure

**Template:** `Templates/CodeFiles/Class.template`

```php
<?php declare(strict_types=1);

namespace [[namespace]] {
    class [[className]] {
[[methods]]
    }
}
```

**Generated Example:**

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\Dashboard {
    class DashboardJsGen {
        public static function main(): string {
            return '/assets/application/gen/js/Dashboard.main.8f14e45f.gen.js';
        }
        public static function settings(): string {
            return '/assets/application/gen/js/Dashboard.settings.2c6ee2b2.gen.js';
        }
    }
}
```

### Naming Convention

| Bundle Type | JS Class | CSS Class |
|-------------|----------|-----------|
| Framework | `FrameworkJsGen` | `FrameworkCssGen` |
| Dashboard | `DashboardJsGen` | `DashboardCssGen` |
| Foreground | `ForegroundJsGen` | `ForegroundCssGen` |

### Method Names

Methods are named after the source file (without extension):

- `Auth.ts` → `AuthJsGen::auth()`
- `GridTable.ts` → `FrameworkJsGen::gridtable()`
- `Dashboard.main.ts` → `DashboardJsGen::main()`

---

## Data Flow: PHP ↔ Build System

### 1. Entry Point Discovery

```
┌─────────────────┐
│   GetApp.php    │  Returns active App instance
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ PrepareParams.php│ Calls $app->toArray()
└────────┬────────┘
         │
         │ Returns IAppInfo as JSON
         ▼
┌─────────────────┐
│ rspack config  │  Parses JSON, builds entry map
└─────────────────┘
```

### 2. IAppInfo Structure

The PHP app returns this data structure:

```json
{
    "bundles": [
        {
            "namespace": "PHPCraftdream\\MyApp\\Foreground",
            "bundleDir": "/path/to/MyApp/Foreground",
            "bundleName": "Foreground",
            "frontendDir": "/path/to/MyApp/Front",
            "isFrameworkBundle": false
        },
        {
            "namespace": "PHPCraftdream\\Garnet\\Bundle",
            "bundleDir": "/path/to/garnet-framework/Bundle",
            "bundleName": "Framework",
            "frontendDir": "/path/to/garnet-framework/Bundle/Front",
            "isFrameworkBundle": true
        }
    ],
    "publicDir": "/path/to/MyApp/Public",
    "assetsDir": "/path/to/MyApp/Public/assets/myapp",
    "assetsDirFwJs": "/path/to/MyApp/Public/assets/framework/gen/js",
    "assetsDirFwCss": "/path/to/MyApp/Public/assets/framework/gen/css"
}
```

### 3. Entry Point Generation

```typescript
// rspack.config.ts
export const makeEntry = (appInfo: IAppInfo) => {
    const entry: Record<string, string> = {};

    appInfo?.bundles?.forEach((bundleInfo: IBundleInfo) => {
        const dir = path.resolve(bundleInfo.frontendDir, 'Scripts');

        fs.readdirSync(dir)
            .filter((f) => /.*.[tj]sx?$/ig.test(f))
            .forEach((file) => {
                const key = bundleInfo.isFrameworkBundle
                    ? file.name.toLowerCase()
                    : `${bundleName}.${file.name.toLowerCase()}`;

                entry[key] = path.resolve(dir, file.base);
            });
    });

    return entry;
};
```

---

## Cache Busting

Assets use content-based hashing:

```
[name].[contenthash:16].gen.js
```

Example: `dashboard.main.8f14e45fceea167.gen.js`

When file content changes:
1. Hash changes automatically
2. New file is generated
3. PHP class is regenerated with new path
4. Old files are cleaned (rspack `clean: true`)

---

## Usage in Twig Templates

```twig
{# Load JS #}
<script src="{{ FrameworkJsGen::auth() }}"></script>
<script src="{{ DashboardJsGen::main() }}"></script>

{# Load CSS #}
<link rel="stylesheet" href="{{ FrameworkCssGen::framework() }}">
<link rel="stylesheet" href="{{ DashboardCssGen::main() }}">
```

---

## Usage in PHP Code

```php
use PHPCraftdream\Dashboard\DashboardJsGen;
use PHPCraftdream\Garnet\Bundle\FrameworkCssGen;

class SomeController {
    public function renderPage() {
        return $this->twig->render('page.twig', [
            'jsMain' => DashboardJsGen::main(),
            'cssFramework' => FrameworkCssGen::framework(),
        ]);
    }
}
```

---

## File Locations

| Generated Class | Location |
|-----------------|----------|
| `*JsGen.php` | `{bundleDir}/{BundleName}JsGen.php` |
| `*CssGen.php` | `{bundleDir}/{BundleName}CssGen.php` |

Example:
- `Apps/Dashboard/DashboardJsGen.php`
- `Bundle/FrameworkJsGen.php`

---

## Environment Variable

The build requires `COMMON_GARNET_WEB_DIR` environment variable:

```typescript
// rspack.config.ts
if (!process.env.COMMON_GARNET_WEB_DIR) {
    console.error('Empty process.env.COMMON_GARNET_WEB_DIR');
    process.exit();
}
```

This points to the directory containing `PrepareParams.php`.
