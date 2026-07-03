# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`setono/sylius-gift-card-plugin` — a Sylius plugin adding gift card functionality. Version 1.x (branch `1.x`) is a full rewrite targeting Sylius 1.13/1.14, PHP >= 8.1, Symfony ^6.4. See `REWRITE.md` for the complete architecture plan, decisions, and progress log of the rewrite.

Key feature set: customers buy gift cards choosing the amount themselves (virtual = email delivery, physical = shipped + design chosen by the customer); redeeming a gift card produces either an order adjustment or a real Payment entity depending on the `setono_sylius_gift_card.redemption.mode` config (`adjustment` | `payment`). No API layer.

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

## UI verification with Playwright MCP

All UI changes MUST be verified with the Playwright MCP tools (configured in `.mcp.json`): run the test application, then use browser navigation and screenshots to confirm the change renders and behaves correctly — product page gift card form (amount, message, design picker, live preview), cart, checkout in both redemption modes, and the admin panel (gift cards, designs, balance dashboard).

## Architecture

Namespace `Setono\SyliusGiftCardPlugin\` maps to `src/`; tests are `Setono\SyliusGiftCardPlugin\Tests\` in `tests/`. Bundle class `src/SetonoSyliusGiftCardPlugin.php`; services are XML files under `src/Resources/config/services/` imported by `services.xml`. The DI extension prepends configuration for other bundles (winzou state machine, sylius_ui, sylius_grid, liip_imagine, sylius_mailer) from YAML files in `src/Resources/config/prepend/` — host apps do not import plugin config manually. Register the bundle before SyliusGridBundle.

Only doctrine/orm is supported. Resources: `gift_card`, `gift_card_design` (translatable, images with front|back types), `gift_card_transaction` (append-only balance ledger, written only by the balance operator).

### Domain rules

- `GiftCard.amount`/`initialAmount` are integers in minor units (Sylius money convention). `initialAmount` is set explicitly — no implicit seeding.
- A disabled, "pending" GiftCard is created at add-to-cart (one per OrderItemUnit) carrying amount/message/design/deliveryType; a reconciliation pass at checkout complete creates cards for quantity-bumped units, removes stale ones, and re-snapshots final amounts from unit totals. Cards are enabled on payment and emailed (all delivery types); disabled on order cancel.
- `deliveryType` (virtual|physical) is derived from `variant->isShippingRequired()` — never from product structure assumptions.
- Balance mutations go through the balance operator exclusively, which writes `GiftCardTransaction` ledger rows (idempotency via nullable-unique `idempotencyKey`). Nothing below controllers flushes.
- Redemption is strategy-based: `adjustment` mode creates negative `order_gift_card` adjustments; `payment` mode creates one Payment per card (offline gateway payment method, lazily created). Balance is committed at order placement, restored on cancel/refund.
- Gift cards cannot pay for gift-card line items (EligibleTotalCalculator default).
