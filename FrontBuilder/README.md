# FrontBuilder

The frontend **toolchain** for Garnet — rspack configs, the codegen plugin,
entry-point resolution and the shared `node_modules`. It contains no source
components of its own: every frontend lives next to its backend code.

```
Bundle/Front/                      -- framework system-bundle UI (auth, settings, ...)
Kernel/Io/GarnetCli/Admin/Front/   -- the /__garnet/ control-plane UI
Apps/MyApp/Front/                  -- MyApp app frontend
Apps/MyApp/Dashboard/Front/        -- bundle-specific frontend
...
```

`FrontBuilder/build/` enumerates those source dirs, generates per-bundle
entry points, and emits compiled bundles into each app's `Public/assets/`.

## Layout

```
FrontBuilder/
  build/                toolchain: rspack codegen (PhpClassGeneratorPlugin), entry resolution
  docs/                 architecture docs (build, SPA, php integration, i18n, api helpers)
  node_modules/         the single shared install for the whole frontend stack
  package.json,
  rspack.config.ts,     production / dev build for app + framework bundles
  rspack.admin.config.ts  build for the /__garnet/ admin panel
  tsconfig*.json        workspace root for tsc / tsgo
  tailwind.config.js
```

## Why everything is here

The Garnet build pipeline expects a single `node_modules` and a single
rspack config for the whole stack. Source files live next to their backend
code (`Bundle/Front/`, `Kernel/Io/GarnetCli/Admin/Front/`, `Apps/<App>/Front/`);
`FrontBuilder/build/` enumerates them, generates per-bundle entry points, and
emits compiled bundles into each app's `Public/assets/`. The framework root's
`node_modules` is a junction to `FrontBuilder/node_modules` (created by
`php garnet setup`) so tsgo and the shared `Bundle/Front` sources resolve
packages without per-package path mappings.

## Common commands

```bash
php garnet build         # production build
php garnet build:watch   # incremental dev build
```

Both delegate to `rspack` configs in this directory. See `docs/02-build-system.md`
for the full pipeline.
