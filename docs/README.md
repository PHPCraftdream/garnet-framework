# Documentation index

The framework ships with the docs below. Start at **Quickstart**;
return here when you need a specific topic.

## Start here

| | |
|---|---|
| [**Quickstart**](guides/quickstart.md) | Scaffold a new app and get to "homepage loads" in under five minutes. |
| [**Getting started**](guides/getting-started.md) | A slower walk through the same flow, with more context. |
| [**Dev workflow**](guides/dev-workflow.md) | Develop framework and app side by side via a Composer path repo + symlink. |

## Architecture

| | |
|---|---|
| [**Architecture**](reference/architecture.md) | Layers, request lifecycle, async DB, design patterns. |
| [**Bundle**](reference/layers/bundle.md) | How a bundle is structured and how `BaseBundleInit` boots one. |
| [**Core**](reference/layers/core.md) | The `Kernel/Core/` primitives: env, benchmarks, events, globals. |
| [**IO**](reference/layers/io.md) | Router, IniConfig, Twig, Logger, Cache, Emitter. |

## Data and forms

| | |
|---|---|
| [**Database**](reference/layers/database.md) | `DbPool`, `DbTable`, async queries, `Account`, EAV. |
| [**Frontend**](reference/layers/frontend.md) | React islands, asset codegen, Tailwind, time/locale. |
| [**i18n**](reference/i18n.md) | Translation pipeline, `%s` interpolation rules, codegen. |
| [**FrontBuilder internals**](../FrontBuilder/docs/README.md) | Deep dive into the build engine itself: Rspack config, SPA architecture, `*Gen.php` codegen. |

## Operations

| | |
|---|---|
| [**CLI**](reference/cli.md) | Every `php garnet <command>`. |
| [**Deploy**](guides/deploy.md) | `garnet bundle` and `deploy:diff` flows. |
| [**SSH**](reference/ssh.md) | Configuring `ssh.ini` for remote deploy commands. |

## Testing

| | |
|---|---|
| [**Testing**](guides/testing.md) | Running kahlan specs, writing new ones, mocking, the contract-spec pattern, and kahlan vs e2e. |
| [**E2E testing**](guides/e2e-testing.md) | Playwright structure, per-worker DB isolation, and how a scaffolded app's CI runs e2e. |

## Cookbook

Short, copy-friendly recipes for the most common tasks:
[**Cookbook index**](cookbook/README.md) — add a bundle, route, island, CLI command, admin entity, parallel queries, email, file upload, validation rules, i18n strings.

## Reference

| | |
|---|---|
| [**Known issues**](known-issues.md) | Sharp edges, workarounds. |
