# FrontBuilder Documentation

## Overview

FrontBuilder is the frontend engine of the Garnet Framework. It implements an SPA architecture on top of server-side Twig rendering.

## Documentation Index

|| File | Description |
|------|-------------|
| [01-architecture.md](01-architecture.md) | Overall directory architecture |
| [02-build-system.md](02-build-system.md) | Build system (Rspack + Tailwind) |
| [03-php-integration.md](03-php-integration.md) | PHP integration, `*Gen.php` class generation |
| [04-api-helpers.md](04-api-helpers.md) | API helpers (`sendPost`, `sendPostFormData`), use from inline JS |
| [06-frontend-engine.md](06-frontend-engine.md) | Frontend engine (Component, FormTool, GridTable, etc.) |
| [07-spa-architecture.md](07-spa-architecture.md) | SPA architecture |

## Quick Start

```bash
# Production build
cd FrontBuilder
COMMON_GARNET_WEB_DIR=/path/to/your-app npx cross-env NODE_ENV=production npx rspack build --config rspack.config.ts

# Development watch mode
COMMON_GARNET_WEB_DIR=/path/to/your-app npx cross-env NODE_ENV=development rspack build --watch --config rspack.config.ts
```

## Key Concepts

### It is already an SPA

The application uses a hybrid approach:
- HTML is rendered on the server (Twig) → SEO, fast FCP
- JS swaps content without a full reload → SPA UX
- REST API for forms and tables

### Core components

|| Component | Purpose |
|-----------|---------|
| `Component` | Base class with lifecycle (`init` / `onRemove`) |
| `PageLoader` | SPA transitions between pages |
| `HotClick` | Intercepts links for reload-free navigation |
| `FormTool` | Form validation and submission |
| `GridTable` | Editable tables |
| `sendPost` | REST API calls |
