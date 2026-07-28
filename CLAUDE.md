# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`setono/sylius-gift-card-plugin` — a Sylius plugin adding gift card functionality (buy gift cards, spend them on orders, check balances, admin management). PHP ^8.2, Symfony ^6.4 || ^7.4, Sylius ^2.0. Development branch: `sylius-2`.

Note: this plugin does not expose an ApiPlatform API — that layer was dropped during the Sylius 2 migration and is intentionally out of scope.

## Commands

```bash
composer analyse           # Psalm static analysis (psalm.xml, baseline in psalm-baseline.xml)
composer check-style       # ECS check (sylius-labs coding standard, config in ecs.php)
composer fix-style         # ECS auto-fix
composer phpunit           # PHPUnit test suite (tests/Unit)
vendor/bin/phpunit tests/Unit/Path/To/SomeTest.php   # single test file
vendor/bin/phpunit --filter testMethodName            # single test method
vendor/bin/phpspec run     # phpspec specs (spec/ directory)
vendor/bin/behat           # Behat acceptance tests (requires running test app, see below)
```

### Test application

`tests/TestApplication/` holds only this plugin's overrides (entity models, repositories, config, template overrides) on top of the `sylius/test-application` package (the actual Sylius 2 test kernel lives in `vendor/sylius/test-application`). Its `.env` sets `SYLIUS_TEST_APP_BUNDLES_REPLACE_PATH`/`SYLIUS_TEST_APP_CONFIGS_TO_IMPORT`/`SYLIUS_TEST_APP_ROUTES_TO_IMPORT`, read by `Sylius\TestApplication\Kernel`. `tests/TestApplication/config/bundles.php` is a full bundle list (not just plugin additions) because the plugin bundle must be registered before `SyliusGridBundle` for its resource parameters to exist when grid config loads — the additive `SYLIUS_TEST_APP_BUNDLES_PATH` env var would append it after core bundles instead.

Local dev can run entirely in Docker: `compose.yml` + `compose.override.dist.yml` (copy to `compose.override.yml`, gitignored) bring up php/mysql/nginx/chrome/chrome-proxy/mailhog; `behat.sh` runs Behat with `APP_ENV=test` inside the container. `docker/` and `behat.sh`/`compose.test.yml` are gitignored local-dev conveniences, not committed.

CI (`.github/workflows/build.yaml`) runs a single job/matrix-cell (PHP 8.4, Symfony ^7.4, Sylius ~2.2.0): composer validate, container/yaml/twig lint, ecs, psalm, phpspec, phpunit, then boots the real app (MySQL, node assets, headless Chrome) and runs Behat.

## Architecture

Namespace `Setono\SyliusGiftCardPlugin\` maps to `src/`; tests are `Setono\SyliusGiftCardPlugin\Tests\` in `tests/`. Bundle class is `src/SetonoSyliusGiftCardPlugin.php` — it overrides `getPath()` to return the repo root (not `src/`) and `getConfigFilesPath()` to `config/doctrine/model`, so plugin resources live as root-level siblings of `src/` (Sylius 2 convention, matching `sylius/sylius`'s own plugins), not nested under a Sylius-1.x-style `src/Resources/` tree:

- `config/` — DI service XML (`services.xml` importing `config/services/*.xml`), `app/config.yaml` (+ `app/fixtures.yaml`), `grids.yaml` (+ `grids/*.yaml`), `routes.yaml`/`routes_no_locale.yaml` (+ `routes/*.yaml`), `sylius_ui.yaml` (now `sylius_twig_hooks` config, despite the filename), `doctrine/model/*.orm.xml`, `serialization/*.xml`, `validation/*.xml`
- `templates/` — Twig views (`Admin/`, `Shop/`, `Email/`, plus `templates/bundles/...` reference overrides for host apps to copy)
- `translations/`, `public/` (assets copied via `assets:install`), `fixtures/` (fixture data files)
- `src/DependencyInjection/SetonoSyliusGiftCardExtension.php` loads `config/services.xml` via a `FileLocator` pointed at `../../config` relative to itself

The bundle must still be registered before `SyliusGridBundle` in host apps (parameter resolution order) — this is unrelated to the resource-path convention above.

### Domain model

`GiftCard` holds a code, `amount`/`initialAmount` (integers, minor units per Sylius money convention), currency, channel, optional customer/expiry, and an enabled toggle. When bought in the shop it is linked 1:1 to an `OrderItemUnit`. `GiftCardConfiguration` (+ `GiftCardChannelConfiguration` join entity) defines per-channel/locale settings: PDF template, default validity period, images.

Host applications integrate by applying the plugin's traits/interfaces to their entities — `ProductTrait` (adds `isGiftCard` and `giftCardAmountConfigurable` flags, PHP attributes not annotations), `OrderTrait` (applied gift cards collection), `OrderItemTrait`, `OrderItemUnitTrait` (gift card relation), and repository traits in `src/Doctrine/ORM/`. The README documents the exact setup; `tests/TestApplication/` shows a working example.

### Two distinct gift card flows

**Buying a gift card** (product flagged as gift card): the gift card entity is created at add-to-cart time via `GiftCardFactory::createFromOrderItemUnitAndCart()`, called from `Form/Extension/AddToCartTypeExtension` (shop form flow, POST_SUBMIT listener). The card starts disabled; Symfony Workflow event listeners (`src/EventListener/Workflow/*`, tagged on `workflow.<graph>.completed.<transition>`) drive its lifecycle through `Operator/OrderGiftCardOperator`: checkout complete → `associateToCustomer`, payment paid → `enable` + `send` (email with PDF), order cancel → `disable`. Sylius 2 defaults to the `symfony_workflow` state machine adapter (not winzou), which is why these are plain kernel event listeners rather than winzou callback config. "Configurable" gift card products let the customer choose the amount.

**Spending a gift card**: `Applicator/GiftCardApplicator` validates (enabled, not expired, channel matches) and attaches the card to the order, then reprocesses it. `OrderProcessor/OrderGiftCardProcessor` (a Sylius order processor) converts each applied card into a negative order adjustment (`AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT`, origin code = gift card code) capped at the eligible order total. Actual balance mutation happens via the same workflow listeners, calling `Modifier/OrderGiftCardAmountModifier`: decrement on order create, increment back on cancel. Both flows can coexist on one order.

### PDF rendering

Gift cards render to PDF via knp-snappy/wkhtmltopdf (`Renderer/PdfRenderer`), with template content and rendering options coming from the gift card's configuration (`Provider/`). Admin supports live preview of example PDFs.

### UI hooks

Sylius 2 replaced the old `sylius_ui: events:` (SonataBlock-style) system with `sylius/twig-hooks`. `config/sylius_ui.yaml` (filename kept for continuity) now declares `sylius_twig_hooks` entries for the shop add-to-cart gift-card fields and the cart-summary applied-gift-cards block. The admin CRUD javascripts (image preview, live PDF rendering, send-customer-email checkbox) and the shop account gift card index hooks still use the removed block system and need porting to twig-hooks — see the `TODO` comment in `config/app/config.yaml`.

### Test layout

- `tests/Unit/` — PHPUnit (includes DI/config tests using matthiasnoback's symfony-config-test / dependency-injection-test)
- `spec/` — phpspec, mirrors `src/` structure
- `features/` + `tests/Behat/` — Behat contexts/pages built on the Sylius Behat pack
