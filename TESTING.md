# Testing Mega Builder

CI (`.github/workflows/ci.yml`) runs all of this on every pull request.

| Suite | What it covers | Command | Needs |
|---|---|---|---|
| PHPUnit (`tests/php`) | Validator, sanitizers, preview tokens, compiler, targeting, media checks | `composer install && vendor/bin/phpunit` | PHP 8.1+ and Composer |
| Vitest (`tests/ui`) | Studio store: insert/move/duplicate/remove, undo/redo, save requests | `npm run test:ui` | Node 22 |
| Typecheck + build | Vue/TS types; the committed `views/dist` bundle matches the source | `npm run typecheck && npm run build` | Node 22 |
| Playwright (`tests/e2e`) | Real store: drag a widget, publish, check the storefront at 1440 / 768 / 390 px, draft preview lock, CSRF | see below | Docker |

`npm test` runs Vitest, then PHPUnit. It **fails** when PHP or `vendor/` is missing, instead of reporting tests it never ran (set `MB_PHP_BIN` if `php` is not on PATH).

## End-to-end against a disposable store

```bash
npm ci
PS_IMAGE=prestashop/prestashop:9.2-apache bash tests/e2e/setup-store.sh   # or 8.2-apache
npx playwright install chromium
npx playwright test
docker compose -f tests/e2e/docker-compose.yml down -v
```

The store runs at http://localhost:8080 with the back office at `/admin-dev`. Its admin login is a
throwaway test account defined in `tests/e2e/docker-compose.yml` and `tests/e2e/env.ts`.
To run the suite against another **test** store, set `MB_E2E_BASE_URL`, `MB_E2E_ADMIN_PATH`,
`MB_E2E_ADMIN_EMAIL` and `MB_E2E_ADMIN_PASSWORD`. The suite publishes and then deletes one homepage template.
