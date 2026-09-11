# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`setono/sylius-gift-card-plugin` — a Sylius plugin adding gift card functionality. Version 1.x (branch `1.x`) is a full rewrite targeting Sylius 1.13 and up, PHP >= 8.1, Symfony ^6.4.

Key feature set: customers buy gift cards choosing the amount themselves (virtual = email delivery, physical = shipped + design chosen by the customer); redeeming a gift card creates a real Payment entity against the order, leaving the order total intact — a gift card settles a liability the shop already took money for, so it is a means of payment rather than a discount. No API layer.

## Commands

```bash
composer analyse           # PHPStan at max level (phpstan.neon)
composer check-style       # ECS check (ecs.php)
composer fix-style         # ECS auto-fix
composer phpunit           # full PHPUnit suite
vendor/bin/phpunit --testsuite unit        # unit tests only (no database needed)
vendor/bin/phpunit --testsuite functional  # functional tests (require MySQL, see below)
vendor/bin/phpunit tests/Unit/Path/To/SomeTest.php   # single test file
vendor/bin/phpunit --filter testMethodName           # single test method
vendor/bin/rector --dry-run                # rector check (CI runs this)
```

## Test application

`tests/Application/` contains a full Sylius app used as the kernel for PHPUnit (bootstrap `tests/Application/config/bootstrap.php`) and for manual/browser verification. Run its console with `(cd tests/Application && bin/console ...)`. It needs a MySQL database (`DATABASE_URL` in `tests/Application/.env`) and built assets (`yarn install && yarn build` inside tests/Application). Admin credentials after fixtures: username `sylius`, password `sylius`.

**Always run the web server with `symfony serve`** (e.g. `(cd tests/Application && symfony serve -d --port=8080)`) — never `php -S` / `symfony php -S ... router.php`. `symfony serve` handles routing itself (no `router.php` needed) and serves from `public/` automatically.

**Assets: use Node 20** (`tests/Application/.nvmrc` pins it — run `nvm use` before `yarn install`/`yarn build`). The frontend uses `@sylius-ui/frontend` (Dart Sass); Node 22+ breaks the build. `package.json` also pins `jquery` via `resolutions` so the admin JS (`jquery.dirtyforms`) loads — without it the admin console throws `jQuery.dirtyForms is not a function` and JS-driven form submits fail.

## Testing conventions

- Unit tests live in `tests/Unit`, functional tests (KernelTestCase/WebTestCase booting the test app) in `tests/Functional`.
- Use a BDD-style naming convention for test methods (`it_does_something`) with the `@test` annotation or `test` prefix.
- Use Prophecy for mocking (phpspec/prophecy-phpunit), not PHPUnit mock objects.
- Form type tests extend `Symfony\Component\Form\Test\TypeTestCase`.
- Form types should bind to a `data_class` rather than produce an array — so the rules travel with the data instead of with the one form that happens to produce it, and consumers get a typed object instead of an array shape annotation. See `AdjustGiftCardBalanceCommand` / `AddGiftCardToOrderCommand`.
- Validation constraints are declared in XML under `src/Resources/config/validation/`, one file per class, never as PHP attributes — models and command objects alike. Host applications override plugin validation by pointing at these files, which only works if every constraint lives there.

## UI testing with Playwright

UI MUST be covered by **Playwright tests**, not just looked at once. Ad-hoc checking only ever exercises the page you happened to change, so regressions on every other page go unnoticed — a dropped option in the test app's `_details.html.twig` override left *every* non-simple product's admin edit page returning a 500, and manual verification of the gift card pages never touched it.

The suite lives in `tests/Playwright` and runs against a served `tests/Application`:

```bash
(cd tests/Application && symfony serve -d --port=8080)   # serve the app first
cd tests/Playwright && yarn install && npx playwright install chromium
npx playwright test                       # whole suite
npx playwright test --project=admin       # admin specs only
npx playwright test --headed -g 'cart'    # watch a single test
```

`PLAYWRIGHT_BASE_URL` overrides the default `https://127.0.0.1:8080`. The admin specs share a signed-in session created by `specs/auth.setup.js`; the shop specs run anonymously. Specs must **discover their subjects** (grid links, locale switcher) rather than hardcode ids, codes or locales, so they keep working against a freshly seeded database.

Any new UI needs a spec here. Coverage today: admin gift cards index/show/edit, designs index/edit, balance report, gift card and design preview PDFs, product edit for simple/configurable/gift card products, and the shop gift card product page, locales, add-to-cart and redemption.

Redemption does not change what the order costs — the gift card becomes a payment against it — so specs assert the "Remaining to pay" figure rather than expecting the order total to drop.

When a test app template overrides a Sylius one, diff it against the original in `vendor/sylius/sylius/.../Resources/views/` before trusting it; the override silently drifts as Sylius changes, and options dropped from a `form_row` call fail only at render time.

Use the Playwright MCP tools (configured in `.mcp.json`) while developing a change, but land the coverage as a spec.

## Architecture

Namespace `Setono\SyliusGiftCardPlugin\` maps to `src/`; tests are `Setono\SyliusGiftCardPlugin\Tests\` in `tests/`. Bundle class `src/SetonoSyliusGiftCardPlugin.php`; services are XML files under `src/Resources/config/services/` imported by `services.xml`. The DI extension prepends configuration for other bundles (winzou state machine, sylius_ui, sylius_grid, liip_imagine, sylius_mailer) as PHP arrays built in `prepend()` — host apps do not import plugin config manually. Register the bundle before SyliusGridBundle.

State machine callbacks are registered twice, once as winzou callbacks in `prepend()` and once as Symfony Workflow subscribers in `src/EventSubscriber/Workflow/`, so the plugin works under either adapter. Keep the two in sync when changing them.

Anything reacting to a Symfony event is an **event subscriber**, so the event name and priority live in `getSubscribedEvents()` next to the code rather than in a service tag. `kernel.event_listener` is only used where a subscriber is impossible — currently Sylius' own `ImagesUploadListener`, which we cannot add the interface to. Doctrine's `onFlush` listener is a Doctrine event, not a Symfony one, and stays a listener.

Only doctrine/orm is supported. Resources: `gift_card`, `gift_card_design` (translatable, images with front|back types), `gift_card_transaction` (append-only balance ledger, written only by the balance operator).

### Domain rules

- `GiftCard.amount`/`initialAmount` are integers in minor units (Sylius money convention). `initialAmount` is set explicitly — no implicit seeding.
- A disabled, "pending" GiftCard is created at add-to-cart (one per OrderItemUnit) carrying amount/message/design/deliveryType; a reconciliation pass at checkout complete creates cards for quantity-bumped units, removes stale ones, and re-snapshots final amounts from unit totals. Cards are enabled on payment and emailed (all delivery types); disabled on order cancel.
- `deliveryType` (virtual|physical) is derived from `variant->isShippingRequired()` — never from product structure assumptions.
- Balance mutations go through the balance operator exclusively, which writes `GiftCardTransaction` ledger rows (idempotency via nullable-unique `idempotencyKey`). Nothing below controllers flushes.
- Redeeming a gift card creates one Payment per card against the order (offline gateway payment method, lazily created), leaving the order total intact. Balance is committed at order placement, restored on cancel/refund.
- Gift cards cannot pay for gift-card line items (EligibleTotalCalculator default).
