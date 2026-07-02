# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`setono/sylius-gift-card-plugin` — a Sylius plugin adding gift card functionality (buy gift cards, spend them on orders, check balances, admin management, API Platform support). PHP >= 8.1, Symfony 5.4 || 6.4, Sylius ~1.12. Development branches follow the `0.*.x` naming scheme (current: `0.12.x`).

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

Note: the README mentions `composer tests`, `composer try` and `composer all` — those scripts do not exist in composer.json; use the commands above.

### Test application

`tests/Application/` contains a full Sylius app used as the kernel for PHPUnit (bootstrap `tests/Application/config/bootstrap.php`) and Behat. Run its console with `(cd tests/Application && bin/console ...)`. Behat (behat.yml.dist) expects the app served at `http://localhost:8080`, a MySQL database, built assets (`yarn install && yarn build` inside tests/Application), and headless Chrome for `@javascript` scenarios — see the `integration-tests` job in `.github/workflows/build.yaml` for the full setup sequence.

CI additionally runs `composer validate --strict`, `composer normalize --dry-run`, `composer-require-checker`, `composer-unused`, yaml/twig/container lints, and Doctrine schema validation.

## Architecture

Namespace `Setono\SyliusGiftCardPlugin\` maps to `src/`; tests are `Setono\SyliusGiftCardPlugin\Tests\` in `tests/`. Bundle class is `src/SetonoSyliusGiftCardPlugin.php`; services are XML files under `src/Resources/config/services/` (one per area: applicator, factory, order_processor, ...) imported by `services.xml`. Resource/grid/route/serializer/API configs also live under `src/Resources/config/`. The bundle must be registered before SyliusGridBundle in host apps (parameter resolution order).

### Domain model

`GiftCard` holds a code, `amount`/`initialAmount` (integers, minor units per Sylius money convention), currency, channel, optional customer/expiry, and an enabled toggle. When bought in the shop it is linked 1:1 to an `OrderItemUnit`. `GiftCardConfiguration` (+ `GiftCardChannelConfiguration` join entity) defines per-channel/locale settings: PDF template, default validity period, images.

Host applications integrate by applying the plugin's traits/interfaces to their entities — `ProductTrait` (adds `isGiftCard` and `giftCardAmountConfigurable` flags), `OrderTrait` (applied gift cards collection), `OrderItemTrait`, `OrderItemUnitTrait` (gift card relation), and repository traits in `src/Doctrine/ORM/`. The README documents the exact setup; `tests/Application/` shows a working example.

### Two distinct gift card flows

**Buying a gift card** (product flagged as gift card): the gift card entity is created at add-to-cart time via `GiftCardFactory::createFromOrderItemUnitAndCart()` — called from `Form/Extension/AddToCartTypeExtension` (shop form flow, POST_SUBMIT listener) and `Api/CommandHandler/AddItemToCartHandler` (API flow). The card starts disabled; winzou state machine callbacks (`src/Resources/config/state_machine/`) drive its lifecycle through `Operator/OrderGiftCardOperator`: checkout complete → `associateToCustomer`, payment paid → `enable` + `send` (email with PDF), order cancel → `disable`. "Configurable" gift card products let the customer choose the amount.

**Spending a gift card**: `Applicator/GiftCardApplicator` validates (enabled, not expired, channel matches) and attaches the card to the order, then reprocesses it. `OrderProcessor/OrderGiftCardProcessor` (a Sylius order processor) converts each applied card into a negative order adjustment (`AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT`, origin code = gift card code) capped at the eligible order total. Actual balance mutation happens via state machine callbacks on `Modifier/OrderGiftCardAmountModifier`: decrement on order create, increment back on cancel. Both flows can coexist on one order.

### API

API Platform resources are declared in `src/Resources/config/api_resources/` with messenger commands/handlers in `src/Api/Command` and `src/Api/CommandHandler`. The plugin replaces the input of Sylius' shop `add item to cart` operation with its own `AddItemToCart` command (carrying gift card info); host apps must copy/adjust `Order.xml` as described in the README.

### PDF rendering

Gift cards render to PDF via knp-snappy/wkhtmltopdf (`Renderer/PdfRenderer`), with template content and rendering options coming from the gift card's configuration (`Provider/`). Admin supports live preview of example PDFs.

### Test layout

- `tests/Unit/` — PHPUnit (includes DI/config tests using matthiasnoback's symfony-config-test / dependency-injection-test)
- `spec/` — phpspec, mirrors `src/` structure
- `features/` + `tests/Behat/` — Behat contexts/pages built on the Sylius Behat pack
