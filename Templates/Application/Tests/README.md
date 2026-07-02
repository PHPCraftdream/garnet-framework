# End-to-end tests

Playwright smoke tests for this app. Preconfigured by the Garnet app
template — extend `specs/` as the app grows.

## First-time setup

```bash
cd Tests
npm install
npx playwright install chromium
```

## Running

The tests hit a locally-served instance. Start the dev server first
(Node front + PHP worker pool), then run the suite:

```bash
# from the app root
php garnet serve            # serves on http://localhost:8001

# in another terminal, from the app root
composer test:e2e          # → npm --prefix Tests test
# or directly:
cd Tests && npm test
```

Useful variants:

```bash
npm run test:ui            # interactive Playwright UI
npm run test:headed        # see the browser
npm run report             # open the last HTML report
BASE_URL=https://staging.example npm test   # target a remote box
```

## Quality gate

PHP code is covered by `composer ci` (php-cs-fixer + phpstan) at the app
root. The e2e suite here is a separate, server-dependent step.
