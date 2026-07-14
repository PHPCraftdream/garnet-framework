# Contributing to Garnet Framework

## Getting Started

1. Fork the repository and clone it locally.
2. Install PHP dependencies (run from the repository root):
   ```bash
   composer install
   ```
3. Run the dev server (requires an app directory):
   ```bash
   php bin/garnet serve
   ```

## Code Style

- PSR-12 base with strict types (`declare(strict_types=1)`) in every file.
- Enforced via php-cs-fixer:
  ```bash
  composer cs:check   # dry-run
  composer cs:fix     # auto-fix
  ```

## Static Analysis

PHPStan at level 5:

```bash
composer phpstan
```

## Tests

Kahlan specs live alongside the code in `Spec/` directories:

```bash
vendor/bin/kahlan --config=kahlan-config.php
```

## Pull Request Flow

1. Create a feature branch from `master`.
2. Make your changes; ensure `composer ci` passes (cs:check + phpstan).
3. New code must include tests (Kahlan specs).
4. Open a PR with a clear description of the change and its motivation.
5. One approval required before merge.

## Reporting Issues

Use the [GitHub issue tracker](https://github.com/PHPCraftdream/garnet-framework/issues). Include PHP version, steps to reproduce, and expected vs actual behavior.

For security issues, **do not** open a public issue — see
[`SECURITY.md`](SECURITY.md) for the responsible-disclosure contact.

## Licensing your contribution

Garnet Framework is **dual-licensed under MIT or Apache-2.0**. Unless
you explicitly state otherwise, any contribution you submit for
inclusion in the work shall be dual-licensed as above, without any
additional terms or conditions. (This is the same convention the Rust
ecosystem uses — it lets downstream users pick whichever license fits
their project.)

## Code of conduct

Participation in this project is governed by our
[Code of Conduct](CODE_OF_CONDUCT.md).
