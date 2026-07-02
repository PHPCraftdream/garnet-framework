# Build System (Rspack)

## Overview

FrontBuilder uses Rspack to bundle JS/TS and CSS. Rspack is a high-performance, Rust-based alternative to webpack.

## Build Commands

```bash
# Production build
cd FrontBuilder
COMMON_GARNET_WEB_DIR=/path/to/your-app npx cross-env NODE_ENV=production npx rspack build --config rspack.config.ts

# Development watch mode
cd FrontBuilder
COMMON_GARNET_WEB_DIR=/path/to/your-app npx cross-env NODE_ENV=development rspack build --watch --config rspack.config.ts
```

## Directory Structure

```
FrontBuilder/
├── rspack.config.ts          # Main configuration
├── package.json
├── tsconfig.json
├── tailwind.config.js        # Tailwind CSS config
├── global.d.ts               # Global type declarations
│
├── Framework/                # Shared framework code
│   ├── Scripts/              # TS entry points (Auth, Framework, GridTable)
│   ├── Styles/               # LESS files + Tailwind directives
│   └── Assets/               # Static assets (libraries, images)
│
├── Common/                   # Shared utilities
│   ├── Dom/                  # DOM manipulation (Component, FormTool, etc.)
│   ├── Api/                  # API helpers (sendPost, etc.)
│   ├── Utils/                # Utilities (I18n, Events, etc.)
│   └── Models.ts             # TypeScript interfaces
│
├── {AppName}/                # App-specific bundles (MyApp, Blog, etc.)
│   ├── Common/
│   ├── Dashboard/
│   └── Foreground/
│
└── docs/                     # Documentation
```

## Configuration (rspack.config.ts)

### Entry Points

Entries are discovered dynamically from PHP:

```typescript
// Execute PHP script to get app info
const phpScript = path.resolve(process.env.COMMON_GARNET_WEB_DIR, "PrepareParams.php");
const paramsStr = execSync(`php ${phpScript}`).toString();
const appInfo: IAppInfo = JSON.parse(paramsStr);

// Make JS entries from bundle Scripts/ directories
const entry = makeEntry(appInfo);
// Result: { auth: '...Auth.ts', dashboard.main: '...Dashboard.ts', ... }

// Make CSS entries from bundle Styles/ directories
const cssEntry = makeCssEntry(appInfo);
// Result: { css_framework: '...framework.less', css_dashboard_common: '...common.less', ... }
```

### Output

```typescript
output: {
    filename: "js/[name].[contenthash:16].gen.js",
    path: genOutputPath,  // AppsPublic/{app}/assets/{app}/gen
    cssFilename: (pathData) => `css/${cleanName}.[contenthash:16].gen.css`,
}
```

### Loaders

| File Type | Loader | Notes |
|-----------|--------|-------|
| `.ts`, `.tsx` | `builtin:swc-loader` | SWC for TypeScript |
| `.js`, `.jsx` | `builtin:swc-loader` | SWC for JavaScript |
| `.less` | `less-loader` + `postcss-loader` | LESS + Tailwind |
| `.css` | built-in CSS support | Rspack native |

### PostCSS Pipeline

```typescript
{
    test: /\.less$/,
    use: [
        {
            loader: 'postcss-loader',
            options: {
                postcssOptions: {
                    plugins: [
                        '@tailwindcss/postcss',  // Tailwind CSS v4
                        'autoprefixer',
                        ...(isProduction ? [['cssnano', { preset: 'default' }]] : []),
                    ],
                },
            },
        },
        { loader: 'less-loader' },
    ],
    type: 'css',
}
```

### Aliases

```typescript
resolve: {
    alias: {
        '@common': path.resolve(__dirname, 'Common'),
        '@framework': path.resolve(__dirname, 'Framework', 'Scripts'),
    }
}
```

## PHP Class Generation

After build, PHP classes are generated for type-safe asset references:

### Generated Classes

```php
// FrameworkJsGen.php
namespace PHPCraftdream\Garnet\Bundle;

class FrameworkJsGen {
    public static function framework(): string {
        return '/assets/framework/gen/js/framework.abc123def456.gen.js';
    }
    public static function auth(): string {
        return '/assets/framework/gen/js/auth.def456abc123.gen.js';
    }
}

// DashboardCssGen.php
namespace PHPCraftdream\Dashboard;

class DashboardCssGen {
    public static function common(): string {
        return '/assets/application/gen/css/Dashboard.common.xyz789.gen.css';
    }
}
```

### Usage in Twig

```twig
<script src="{{ FrameworkJsGen::framework() }}"></script>
<link rel="stylesheet" href="{{ DashboardCssGen::common() }}">
```

## Tailwind CSS

### Configuration (tailwind.config.js)

```javascript
module.exports = {
  content: [
    './Common/**/*.ts',
    './Common/**/*.tsx',
    './Bundle/**/*.ts',
    './Bundle/**/*.tsx',
    './**/Scripts/**/*.ts',
    './**/Scripts/**/*.tsx',
  ],
  corePlugins: {
    preflight: false,  // Disable to avoid conflicts with Bootstrap
  },
}
```

### Usage in LESS

```less
// framework.less
@tailwind base;
@tailwind components;
@tailwind utilities;

// Your custom styles
.garnet-form-error {
  cursor: default;
}
```

## Build Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Build Process                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────┐                                               │
│  │ PrepareParams.php │◄── GetApp.php ◄── Apps/{App}/autoload.php   │
│  └────────┬─────────┘                                               │
│           │                                                          │
│           │ IAppInfo (JSON)                                          │
│           ▼                                                          │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                      rspack.config.ts                         │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │  1. makeEntry() ──► JS entry points                          │  │
│  │  2. makeCssEntry() ──► CSS entry points                      │  │
│  │  3. Clear output directories                                 │  │
│  │  4. Run Rspack build                                         │  │
│  └──────────────────────────────────────────────────────────────┘  │
│           │                                                          │
│           │ afterEmit hook                                           │
│           ▼                                                          │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                   phpClassBuilder()                           │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │  1. Process JS chunks ──► *JsGen.php                         │  │
│  │  2. Process CSS chunks ──► *CssGen.php                       │  │
│  │  3. Move framework assets to framework dir                   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  Output:                                                             │
│  ├── AppsPublic/{app}/assets/{app}/gen/js/*.gen.js                  │
│  ├── AppsPublic/{app}/assets/{app}/gen/css/*.gen.css                │
│  └── AppsPublic/{app}/assets/framework/gen/{js,css}/*.* (framework) │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Key Interfaces

### IAppInfo

```typescript
interface IAppInfo {
    bundles: IBundleInfo[];
    namespace: string;
    appDir: string;
    appDirName: string;
    publicDir: string;
    assetsDir: string;
    assetsDirName: string;
    assetsDirFw: string;
    assetsDirFwJs: string;
    assetsDirFwCss: string;
    assetsWebPath: string;
    // ... more paths
}
```

### IBundleInfo

```typescript
interface IBundleInfo {
    namespace: string;
    bundleDir: string;
    bundleName: string;
    frontendDir: string;
    isFrameworkBundle: boolean;
    twigTemplatesDir: string;
    // ... more paths
}
```

## Dependencies

### Production

```json
{
  "dependencies": {
    "gridjs": "^6.x",
    "js-base64": "^3.x",
    "lodash": "^4.x"
  }
}
```

### Development

```json
{
  "devDependencies": {
    "@rspack/cli": "^1.7.6",
    "@rspack/core": "^1.7.6",
    "@tailwindcss/postcss": "^4.x",
    "autoprefixer": "^10.x",
    "cssnano": "^7.x",
    "less": "^4.x",
    "less-loader": "^12.x",
    "postcss": "^8.x",
    "postcss-loader": "^8.x",
    "tailwindcss": "^4.x",
    "typescript": "^5.x"
  }
}
```

## Troubleshooting

### "Empty COMMON_GARNET_WEB_DIR"

Set environment variable:
```bash
COMMON_GARNET_WEB_DIR=/path/to/your-app rspack build --config rspack.config.ts
```

### "Class not found" in GetApp.php

Check that the correct app is active in `GetApp.php`:
```php
require_once __DIR__ . '/Apps/MyApp/autoload.php';
PHPCraftdream\MyApp\MyApp::setPublicDirInit($getPd('MyApp'));
$app = new PHPCraftdream\MyApp\MyApp(false);
```

### CSS not updating

1. Clear output directory (happens automatically on build)
2. Check LESS file imports
3. Verify Tailwind content paths include your files
