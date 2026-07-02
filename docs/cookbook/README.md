# Cookbook

Short, copy-friendly recipes for the most common framework tasks. Each
page is a single small problem with a complete, working solution.

If you're new, read [`../quickstart.md`](../quickstart.md) and
[`../architecture.md`](../architecture.md) first — the recipes assume
you know what a bundle, a controller and a Twig template are.

## Index

### Adding things

- [Add a bundle](add-a-bundle.md) — wire a new self-contained module into your app.
- [Add a route](add-a-route.md) — expose a new URL via a controller method.
- [Add a CLI command](add-a-cli-command.md) — register a `php garnet <name>` subcommand.
- [Add a React island](add-an-island.md) — hydrate a server-rendered placeholder with React.
- [Add an admin entity](add-an-admin-entity.md) — `IEntityConfig` + Template Method controllers for admin CRUD pages.

### Data and IO

- [Run MySQL queries in parallel](parallel-mysql-queries.md) — fan out reads with `DbPool::selectAsync`.
- [Send an email](send-an-email.md) — wire `symfony/mailer` through the bundled mailer service.
- [Upload a file safely](upload-a-file.md) — pending → commit flow via `FileUploadManager`.

### Patterns

- [Add validation rules to a form](add-validation-rules.md) — one source of truth in PHP, auto-Zod on the frontend.
- [Localise UI strings](localise-strings.md) — i18n keys, `%s` interpolation, codegen pipeline.

---

↑ Back to [Documentation index](../README.md)
