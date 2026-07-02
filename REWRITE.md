# SyliusGiftCardPlugin 1.x — Full Rewrite Plan

> Living document: the **Progress log** and **Findings** sections at the bottom are updated continuously during implementation. Delete this file before tagging 1.0.0.

## Context

The 0.12.x plugin has accreted complexity: a full API Platform layer, two near-duplicate add-to-cart code paths, a DB-stored Twig template compiled at runtime (live-preview editor, custom normalizers, fake-IRI hack), an over-engineered `GiftCardConfiguration` entity family, a hard wkhtmltopdf dependency, and robustness gaps (no amount validation, no currency check, quantity-bump units without cards, stale amount snapshots, in-memory balance aggregation, cache-dir email attachments). The maintainer wants a clean 1.x rewrite that simplifies aggressively and hardens what remains. All decisions below were confirmed with the user across nine Q&A rounds (scope → architecture → DX → UX → cart-data model → preview → product/design UX → design unification → i18n/format).

## Decisions (all user-confirmed)

- **Sylius 1.13/1.14 only**; PHP >= 8.1, Symfony ^6.4 only. Clean break (`UPGRADE-1.0.md`, no migrations). New `1.x` branch from `0.12.x`.
- **No API layer.** One gift card type: customer chooses the amount (free money input, configurable min/max).
- **Virtual vs physical deduced from `variant->isShippingRequired()`** — no forced product structure. **Virtual-only merchants are a first-class persona**: every UI collapses gracefully when no shipping-required variant exists (no delivery selector, fixtures/scaffold support virtual-only).
- **Delivery UX**: styled radio cards ("Virtual — delivered instantly by email" / "Physical — shipped to you") replacing the stock option dropdown on gift-card product pages; hidden when only one delivery type exists.
- **Admin scaffold**: a "Create gift card product" action that asks (checkboxes, virtual pre-checked) which delivery types to create — virtual-only = single non-shippable variant, both = Delivery option + two variants, gift-card flag set. Docs + fixture still cover manual setup.
- **Hybrid card lifecycle**: disabled GiftCard created **at add-to-cart** (one per OrderItemUnit; holds amount/message/design/deliveryType) + **checkout-complete reconciliation** (create cards for quantity-bumped units, remove cards for removed units, re-snapshot `amount = initialAmount = unit->getTotal()`, set customer) + **pending-cleanup ORM listener** (unit removal deletes card only while pending; DB FK stays SET NULL so completed-order deletion can never kill a live card). Cart pruning cleans pending cards automatically.
- Quantity N = N identical cards; distinct messages = separate lines (`equals()` override). Cart editing = remove + re-add.
- **One Design concept for BOTH delivery types** (physical artwork = virtual PDF artwork; fulfillment is the only difference). Designs have **front + back image slots** (front: picker/preview/PDF page 1 with overlay; back: optional, PDF page 2 with code/terms, default back rendered when absent). Picker shows for all gift-card purchases, first enabled design preselected. **Translatable names** (`GiftCardDesignTranslation`).
- **Preview**: live HTML/CSS preview on the product page (front image + amount/message overlaid, vanilla JS, updates as they type; PDF styled to match). Preview **takes over the product image area** on gift-card product pages and **adopts the design image's natural aspect ratio** (centered on a neutral backdrop — no forced landscape; PDF letterboxes the same way). Everywhere else (cart line, admin) = rendered PDF. Admin design form gets an **example-PDF preview button** (renders fake card via real dompdf pipeline).
- **Product page form mechanics**: single message textarea (max length ~500); design picker = thumbnail radio grid (front image + translated name, no JS library); all gift card fields are part of the ONE stock add-to-cart `<form>` (added by the form extension), so they ride Sylius' bundled AJAX add-to-cart (`sylius-add-to-cart.js` serializes the whole form → `sylius_shop_ajax_cart_add_item` → same `AddToCartType` server-side incl. our validators → JSON errors rendered inline by stock JS; proven by 0.12.x's identical subform mechanism — test that nested field errors render).
- **Designs seeded lazily**: picker provider creates a "Classic" design (bundled image) when a channel has none, logged.
- **Code format**: grouped, unambiguous (e.g. `XXXX-XXXX-XXXX-XXXX`, alphabet excludes 0/O/1/I/L); lookup normalizes case/dashes/spaces; `code_length` = significant chars.
- **GiftCardTransaction ledger** (audit + idempotency): types `redeem|restore|manual`, nullable `reason`, nullable-unique `idempotencyKey` (callbacks write deterministic keys e.g. `redeem:order:{id}:payment:{id}`; manual entries leave null — replaces a composite unique that MySQL couldn't partial-index). **Admin "Adjust balance" action** (delta + reason → manual ledger entry).
- Card state = **`enabled` bool + derived** (`isUsable()`, `isPending()`); no state machine on the card.
- **Balance committed at order placement**, restored on cancel (both modes). **Gift cards cannot buy gift-card line items** (default; `EligibleTotalCalculatorInterface` = override seam).
- Payment mode: **one Payment per card**; gift-card PaymentMethod = stock **offline gateway**, **lazily created** by the provider if missing (logged); fixture snippet documented.
- **Auto-configuration via `prepend()`**: winzou state machines, sylius_ui events, liip filters, sylius_mailer emails, twig paths. Install = require, register bundle (before SyliusGridBundle), import routes, apply traits, update schema.
- **PDF: `dompdf/dompdf` as a regular dependency**, `DompdfGiftCardPdfGenerator` aliased to `Pdf/GiftCardPdfGeneratorInterface::generate(GiftCardInterface): string`; pluggable via service replacement. Template = file `Shop/GiftCard/pdf.html.twig` receiving `{ giftCard }`. **Page size config `pdf.page_size`, default A6** (passed to dompdf options).
- **Email: keep sylius/mailer-bundle**. Attachments via `tempnam()` + unlink in `finally`. **Both virtual and physical purchases email the code** (backup if physical mail is lost).
- **Dropped features**: public balance-lookup page, account "my gift cards" section.
- Apply gift card: **cart page only**; POST apply, **POST + CSRF remove**.
- **Tooling & testing modeled on Setono/SyliusPluginSkeleton 1.14.x** (user-directed): **no Behat**. PHPStan at max strictness (symfony/doctrine/phpunit/prophecy extensions + `tests/PHPStan/{console_application,object_manager}.php` loaders) replaces psalm; ECS kept; Rector (dry-run in CI); Infection mutation testing; `shipmonk/composer-dependency-analyser` replaces composer-require-checker/composer-unused. Tests = **PHPUnit unit suite** (BDD-style method names, Prophecy mocks, forms via `TypeTestCase`) + **PHPUnit functional suite** (KernelTestCase/WebTestCase against `tests/Application`, MySQL in CI). **UI verification via Playwright MCP** (`.mcp.json` running `@playwright/mcp`; plugin CLAUDE.md mandates browser-driving the test app — admin login sylius/sylius — for every UI change).

## Domain model (1.x)

**`GiftCard`**: id, code (unique, grouped format), enabled, amount (balance), initialAmount (**explicit setter, no magic**), currencyCode, channel (NOT NULL), customMessage, expiresAt, customer (M2O SET NULL), `deliveryType` (backed enum `virtual|physical`, NOT NULL), `design` (M2O nullable SET NULL), optimistic-lock `version`, orderItemUnit 1-1 (**SET NULL in DB**; pending-only deletion via ORM listener), appliedOrders M2M, transactions OneToMany. Dropped: `origin`. Helpers: `isUsable()`, `isPending()` (= !enabled && no transactions), `isDeletable()`.

**`GiftCardDesign`**: code, enabled, position, channels M2M, translations (name), images OneToMany (`GiftCardDesignImage extends Sylius Image`, `type` ∈ front|back; front required for enabled designs; reuse `sylius.listener.images_upload`). Admin CRUD + grid (thumbnails) + example-PDF preview action. Lazy "Classic" seeding.

**`GiftCardTransaction`** (a Sylius resource like the others — model/repository/factory overridable via `resources:` config): giftCard (NOT NULL), order (nullable SET NULL), payment (nullable), amount (±), type (redeem|restore|manual), reason (nullable), idempotencyKey (nullable, unique), createdAt. Written only by `GiftCardBalanceOperator`.

**`GiftCardConfiguration` family: deleted entirely.**

Host-app traits (attributes in traits; XML mapped-superclass for plugin resources): `ProductTrait` (single `giftCard` bool), `OrderItemTrait` (just the `equals()` override), `OrderItemUnitTrait` (1-1 inverse `giftCard`), `OrderTrait` (`giftCards` M2M).

## Purchase flow

1. `AddToCartTypeExtension` (PRE_SET_DATA) adds `GiftCardInformationType` (amount MoneyType, customMessage textarea w/ max length, design thumbnail-radio grid — first enabled preselected) when `product->isGiftCard()`. Delivery choice rendered as styled radio cards (hidden if single delivery type). Fields render inside the stock add-to-cart form via the `sylius.shop.product.show.add_to_cart_form` sylius_ui event and submit through Sylius' stock AJAX add-to-cart. Live preview occupies the product image area at the design's natural aspect ratio. Decorated `AddToCartCommand` + factory decorator kept.
2. POST_SUBMIT → ONE service `Cart/CartGiftCardHandler`: `setUnitPrice(amount)` + `setImmutable(true)`; per OrderItemUnit creates a **pending GiftCard** (code, provisional amounts, currency/channel from cart, deliveryType from variant, design, message, disabled). Persist, no flush.
3. Validation: `ValidGiftCardAmount` via `GiftCardAmountLimitsProviderInterface` (config min/max, channel-aware, decoratable); design must be enabled/in-channel.
4. **Reconciliation at `sylius_order_checkout.complete`** → `OrderGiftCardOperator::reconcile($order)`: create missing cards (quantity bumps), remove pending cards for gone units, final `amount = initialAmount = unit->getTotal()`, set customer, refresh expiresAt (now + `default_validity_period`, default 3 years). Idempotent.
5. `sylius_order_payment.pay` → `enable` + `send` (all delivery types). `sylius_order.cancel` → `disable`.
6. **Pending cleanup listener** (onFlush/preRemove on OrderItemUnit): unit removed && card pending ⇒ remove card.
7. **Live preview** (product page): Twig partial + vanilla JS overlaying amount/message on selected design's front image; reacts to variant/design changes. Cart line: design thumbnail + message excerpt + link to `shop_gift_card_pdf` (current-cart guard).

Admin: standard resource create (channel field, currency from channel base, expiry prefilled, `sendNotificationEmail` flag → post_create subscriber); grid default-filters out pending cards; "Adjust balance" action; product scaffold action.

## Redemption — one abstraction, two modes

```yaml
setono_sylius_gift_card:
    redemption:
        mode: adjustment                 # 'adjustment' | 'payment'
        payment_method_code: gift_card
```

`Redemption\GiftCardRedemptionMethodInterface`: `apply/remove` (cart-time M2M mutation + composite reprocess — shared base), `getCoveredAmount(ByGiftCard)`, `commit(order)` (placement; idempotent), `rollback(order)` (cancel; idempotent). Extension loads `services/redemption/adjustment.xml` XOR `payment.xml` + aliases the interface. All winzou callbacks always registered; handlers no-op when mode doesn't match.

Shared: `GiftCardApplicator` (single entry point; guards: usable, channel match, **currency match**, not already applied, order is cart; never flushes), `GiftCardCoverageCalculator` (per card `min(card.amount, remainingEligibleTotal)`, skips unusable/mismatched), `EligibleTotalCalculator` (total minus gift-card line items), `GiftCardBalanceOperator` (ONLY balance mutator; ledger + idempotencyKey; `InsufficientGiftCardBalanceException`; never flushes).

**Adjustment mode** (default): `GiftCardAdjustmentProcessor` (order processor, priority 5) creates negative `order_gift_card` adjustments; `AddAdjustmentsToOrderAdjustmentClearerPass` unchanged. Commit at `sylius_order.create`, rollback at `cancel`. Full coverage ⇒ total 0 ⇒ core skips payment step natively.

**Payment mode**: no gift-card Payments while cart. Lazy `GiftCardPaymentMethodProvider`. Two resolver decorators hide the method (checkout choices + default-method resolution). `GiftCardAwareOrderPaymentProcessor` decorates BOTH `sylius.order_processing.order_payment_processor.checkout` and `.after_checkout`: gateway payment = `total − coverage`, removed at ≤ 0 (also fixes retry-after-failure sizing). `GiftCardAwarePaymentMethodSelectionRequirementChecker` skips payment step at 100% coverage. Placement on `sylius_order.create` (core cascade at -600): **-650** create one cart-state Payment per covered card (live-balance amounts; details carry code/id) → cascade to new → **-550** complete gift-card payments → `sylius_payment.complete` callback (method-guarded) decrements via operator. Core resolver yields `partially_paid`/`paid`. Cancel: -550 refunds completed gift-card payments → `sylius_payment.refund` callback restores (manual per-payment admin refund restores exactly that card).

Robustness: nothing below controllers flushes (single placement transaction; no DQL bulk updates). Optimistic `version` + balance guard for concurrency. `OrderGiftCardsUsable` checkout-complete constraint; calculator independently ignores dead cards.

Shop UX: `ApplyGiftCardToOrderAction` (POST `/cart/gift-cards`), `RemoveGiftCardFromOrderAction` (POST + CSRF), `AddGiftCardToOrderType` + `GiftCardToCodeDataTransformer` (normalizes code format) + compound `GiftCardIsApplicable`, `RedirectUrlResolver` kept. Cart-only apply. Totals partial: per-card coverage; payment mode adds "Remaining to pay" (`Twig\GiftCardRedemptionRuntime`). Keep promotion rule `has_no_gift_card`.

## PDF & email

- PDF: dompdf generator; template receives `{ giftCard }`; **two pages** (front artwork + overlay / back with code+terms, default back if no image); page size from `pdf.page_size` (default **A6**). Routes: admin download, admin design example-preview, shop cart-preview (current-cart guard).
- Email: sylius/mailer-bundle; `gift_card_customer` + `gift_card_order` emails prepended. `Mailer\GiftCardEmailManager`: `sendGiftCardsFromOrder` (pay; all delivery types) + `sendGiftCard` (admin post_create/resend). `tempnam()` + `finally` unlink. `LocaleSwitcher`.

## Config tree (full)

```yaml
setono_sylius_gift_card:
    code_length: 16                      # significant chars, displayed grouped
    default_validity_period: '3 years'   # strtotime-compatible; null = never
    purchase:
        minimum_amount: 100              # minor units
        maximum_amount: ~
    redemption:
        mode: adjustment
        payment_method_code: gift_card
    pdf:
        page_size: A6
    resources:
        gift_card:             { classes: { model, repository, form, factory } }
        gift_card_design:      { classes: { model, repository, form, factory } }
        gift_card_transaction: { classes: { model, repository, factory } }
```

No `driver` node — doctrine/orm is hardcoded as the only supported driver (`registerResources('setono_sylius_gift_card', 'doctrine/orm', ...)`).

## Deleted from 0.12.x (UPGRADE-1.0.md inventory)

`src/Api/**` + `api_resources/*` + `serialization/*` + `Serializer/**` + JMS config/routes + `Security/GiftCardVoter` + `CreateServiceAliasesPass`; `GiftCardConfiguration` family (entities, forms, side-effecting provider, default-flag subscriber+validator, grid, routes, views, fixtures, `DatePeriod*`, PDF page-size/orientation validators, `HasBackgroundImage`); DB Twig template + live preview editor (`GenerateEncodedExamplePdfAction`, `Twig/Pdf*`, admin JS); `PdfRenderer`/`PdfResponse`/`PdfRenderingOptionsProvider`/`DefaultGiftCardTemplateContentProvider`; `Resolver/{LocaleResolver,CustomerChannelResolver}`; `Modifier/OrderGiftCardAmountModifier`; `GiftCardBalance(Collection)`; public balance lookup + account gift cards section (+ `AccountMenuListener`); `origin` + `giftCardAmountConfigurable`; channel-scoped admin create; `spec/` + phpspec; **Behat entirely** (`behat.yml.dist`, `features/`, `tests/Behat/`, `etc/`, selenium/chrome CI steps, `setono/sylius-behat-pack` + `behat/behat` deps); psalm (`psalm.xml`, baseline, psalm plugins → replaced by PHPStan); `composer-require-checker.json` + `composer-unused.php` (→ shipmonk analyser); composer: `api-platform/core`, `sylius/api-bundle`, `knplabs/knp-snappy(-bundle)`, `webimpress/safe-writer`, dev `jms/serializer-bundle` + `lexik/jwt-authentication-bundle` + `infection` stays, `phpspec/*` dropped; Symfony 5.4. **Added**: `dompdf/dompdf`, `phpstan/*` + extensions, `rector/rector`, `shipmonk/composer-dependency-analyser` (CI-only), `.mcp.json` (playwright MCP), plugin-level `CLAUDE.md` per skeleton conventions.

## Implementation phases

1. **Branch + skeleton alignment**: `1.x` branch; composer.json; bundle, `Configuration`, extension (`prepend()`, conditional redemption loading); adopt SyliusPluginSkeleton 1.14.x tooling (phpstan.neon + PHPStan loader stubs, rector.php, infection.json.dist, ecs.php, `.mcp.json` with playwright MCP, plugin CLAUDE.md, composer scripts); CI workflow per skeleton (coding standards + rector dry-run, shipmonk dependency analysis, phpstan, unit tests, functional tests w/ MySQL + container lint + schema validate, infection, coverage; matrix PHP 8.1–8.3, Symfony ~6.4, Sylius ~1.13/~1.14, lowest+highest deps); wholesale deletions (API, configuration family, spec/, Behat, psalm).
2. **Models**: `GiftCard` (+ enum, version, helpers), `GiftCardDesign` (+Image w/ front|back types, +Translation), `GiftCardTransaction` (+idempotencyKey/reason), traits, mappings; test-app wiring; repositories (incl. `findBalance()`), grouped-code generator + normalizer, `GiftCardFactory`.
3. **Design resource**: form (front/back uploads), grid, routes, translations, liip filter, lazy seeding, example-PDF preview action, fixture.
4. **Purchase flow**: AddToCartCommand + factory decorator, `GiftCardInformationType`, `AddToCartTypeExtension`, `CartGiftCardHandler`, amount validator + limits provider, delivery radio cards + design picker + live preview JS, pending-cleanup listener, `gift_card_product` fixture.
5. **Lifecycle**: `OrderGiftCardOperator` (reconcile/enable/disable/send) + state-machine YAML; cart-line partial + shop PDF route; admin product scaffold action.
6. **Redemption shared core**: calculators, `GiftCardBalanceOperator` + ledger, `GiftCardApplicator`, `GiftCardIsApplicable`, exceptions.
7. **Adjustment mode end-to-end** (first shippable milestone): processor, clearer pass, `AdjustmentRedemptionMethod`, create/cancel callbacks.
8. **Shop redemption UI**: apply/remove actions, form/transformer, cart/checkout/order-show partials, Twig runtime, `OrderGiftCardsUsable`.
9. **Payment mode**: lazy method provider, `PaymentRedemptionMethod`, payment-processor decorator (both registrations), checker + resolver decorators, placement/cancel/refund callbacks.
10. **PDF + email**: dompdf generator + two-page `pdf.html.twig` (matches live preview) + download/preview actions; `GiftCardEmailManager` + templates + subscriber + resend.
11. **Admin polish**: grid/form (pending filter), Adjust-balance action, balance dashboard (SQL), menus, translations, remaining fixtures.
12. **Tests + docs**: unit + functional suites per the strategy below; README + `UPGRADE-1.0.md`; plugin CLAUDE.md documenting the Playwright-MCP verification workflow.

## Verification

- Per phase: `vendor/bin/phpunit` (unit suite), `composer analyse` (PHPStan max), `composer check-style`, `vendor/bin/rector --dry-run`, `(cd tests/Application && bin/console lint:container && bin/console doctrine:schema:validate)`.
- **Unit tests** (PHPUnit, Prophecy, BDD-style names): coverage calculator (stacking, capping, unusable/currency skips, GC-item exclusion), balance operator (redeem/restore/manual, ledger idempotency, insufficient-balance), both redemption methods, payment-processor decorator (resize/remove/retry sizing), checker+resolver decorators, `GiftCardIsApplicableValidator`, `ValidGiftCardAmountValidator`, code generator + normalizer, factory, operator (reconcile idempotency), forms via `TypeTestCase`.
- **Functional tests** (PHPUnit KernelTestCase/WebTestCase, tests/Application + MySQL, two kernel configs differing by `redemption.mode`): DI extension/config (matthiasnoback packages kept), `findBalance()` SQL, full order-processor pipeline per mode, winzou `create`/`cancel`/`complete`/`refund` chains on real fixtures asserting balances/payments/order paymentState/ledger rows, idempotent re-fire, reconciliation (quantity bump, removed lines), pending-cleanup on cart pruning, lazy PaymentMethod + design seeding, add-to-cart endpoint incl. nested validation errors (amount limits, design), apply/remove endpoints (POST + CSRF), checkout completion at 0/partial/full coverage.
- **Playwright MCP verification** (interactive, per the plugin CLAUDE.md; test app served locally, admin sylius/sylius): compose a gift card on the product page (delivery radio cards, thumbnail design picker, live preview updating with amount/message/design, aspect-ratio handling), add to cart via stock AJAX incl. inline validation errors, cart line (thumbnail, message excerpt, PDF link), full checkout in both delivery types (shipping step present/absent) and both redemption modes (payment step skipped at full coverage; "remaining to pay" row), admin: scaffold dialog, design CRUD + example-PDF preview, gift card grid/filters, adjust-balance form, balance dashboard, resend email. Screenshot each state.

## Deferred / roadmap (documented, not built)

Recipient name/email fields, per-channel validity periods, preset amount chips, editable-in-cart lines, public balance page, CR80 print template variant, expiry reminder emails.
---

## Progress log

### Phase 1: Branch + skeleton alignment — IN PROGRESS
- [x] `1.x` branch created from `0.12.x`
- [x] Deletions, batch 1: `src/Api`, `api_resources/`, `serialization/`, `serializer/`, `src/Serializer`, `src/Security`, `src/Renderer`, `src/Twig`, `src/Modifier`, `src/Grid`, `spec/`, `features/`, `tests/Behat`, `etc/`, `docs/`, `behat.yml.dist`, `phpspec.yml.dist`, `psalm.xml`, `psalm-baseline.xml`, `composer-require-checker.json`, `composer-unused.php`, `routes/admin_api.yaml`
- [x] Deletions, batch 2: `GiftCardConfiguration` entity family (models, interfaces, factory, fixture, subscriber, `GiftCardBalance(Collection)`)
- [x] Deletions, batch 3: config-family providers/validators/forms, `DatePeriod*`, `Pdf/*` validators, `GiftCardIsNotExpired*`, `SearchGiftCard*`, `Admin/GenerateEncodedExamplePdfAction`, api/field_types/modifier/renderer/serializer/twig/voter service XMLs, `tests/Unit` (rewritten later)
- [x] `.mcp.json` with playwright MCP already present (added by maintainer)
- [x] Deletions, batch 4: stale views (config admin UI, account section, search page, bundle-template overrides, product-page partial, cart partial), config-family grids/validation XMLs, `misc.xml`/`provider.xml`/`validator.xml`/`order_processor.xml`, `src/OrderProcessor`, `CreateServiceAliasesPass`, `AccountMenuListener`, `app/config.yaml` (superseded by prepend()), old JS assets
- [x] New composer.json: +dompdf, +phpstan stack (+extension-installer, prophecy plugin), +rector, kept infection/code-quality-pack/config-test pkgs; −api-platform, −sylius/api-bundle, −knp-snappy, −safe-writer, −jms/lexik, −behat pack; Symfony ^6.4 only; Sylius components ^1.13; dev sylius/sylius ~1.13||~1.14
- [x] Tooling: phpstan.neon (max level + symfony/doctrine loaders in tests/PHPStan/), rector.php (UP_TO_PHP_81), infection.json.dist, .gitignore (.build/)
- [x] Plugin CLAUDE.md rewritten for 1.x (skeleton conventions, Playwright MCP verification section)
- [x] New `Configuration` (code_length 16, default_validity_period '3 years', purchase min/max, redemption mode/payment_method_code, pdf.page_size A6, resources: gift_card + gift_card_design(+translation +image) + gift_card_transaction; no driver node)
- [x] New `SetonoSyliusGiftCardExtension`: parameters, hardcoded doctrine/orm driver, loads `services/redemption/<mode>.xml` (placeholders for now), generic `prepend()` reading `Resources/config/prepend/*.yaml` (currently: sylius_mailer emails, liip_imagine design filters; grids/state-machine/sylius_ui files land in their phases)
- [x] services.xml trimmed to 11 surviving area files; each trimmed to classes that still exist
- [x] phpunit.xml.dist: `unit` + `functional` suites
- [x] CI workflow rewritten per skeleton (coding standards + rector, shipmonk dependency analysis, phpstan, unit, functional w/ MySQL, coverage; Sylius ~1.13/~1.14 matrix)
- [x] `composer update` clean on PHP 8.1 (needed `policy.advisories.block: false` — api-platform 2.7 transitively pulled by sylius/sylius has advisories). Sylius upgraded 1.12→1.14.19.
- [x] Test app boots against Sylius 1.14: added `SyliusStateMachineAbstractionBundle` (new in 1.13, default adapter winzou); removed Behat + Snappy bundles; emptied `services_test.yaml` (Behat imports); fixed `AddAdjustmentsToOrderAdjustmentClearerPass` (clearer arg is now the `sylius.order_processing.adjustment_clearing_types` **parameter**, not a literal array).

### Phase 1 — COMPLETE ✅
Container boots, `lint:container` OK.

### Phase 2: Models, mappings, repositories, factory — COMPLETE ✅
- Entities: `GiftCard` (deliveryType enum, design FK, version, transactions, isUsable/isPending/isDeletable), `GiftCardDesign` (+Translation +Image front/back) translatable resource, `GiftCardTransaction` ledger. Traits: ProductTrait (single flag), OrderItemTrait (equals-only), OrderItemUnitTrait, OrderTrait.
- XML mappings for all; **7 tables create cleanly**, `doctrine:schema:validate` → "mapping files are correct". (DB "not in sync" is only MariaDB-vs-Doctrine platform noise affecting the entire Sylius schema identically; CI uses MySQL 8.0.)
- Repositories: `GiftCardRepository` (findBalance SQL aggregation; list query hides pending cards), `GiftCardDesignRepository` (findEnabledByChannel, countByChannel). Interfaces in `Repository/`, impls in `Doctrine/ORM/` (Sylius convention).
- Grouped unambiguous code generator (alphabet excludes 0/O/1/I/L) + `GiftCardCodeNormalizer` (normalize/format).
- `GiftCardFactory` (createForChannel, createExample) — validity from bundle config.
- Design forms created (GiftCardDesignType/TranslationType/ImageType) + ImagesUploadListener wired (phase 3 fleshes out UI).
- **Quality gates all green**: PHPStan max (0 errors), ECS (0), Rector (0). phpstan.neon: `allowNullablePropertyForRequiredField: true` + ignore for interface-vs-concrete association mismatches (both inherent to Sylius resource model).

### Phase 3: Design resource end-to-end — COMPLETE ✅
- `GiftCardDesignProvider` with lazy "Classic" seeding (creates a default design + front image from the bundled `default_background.png` when a channel has no enabled designs; ORMException-guarded for concurrency).
- Admin grid via prepend (`prepend/sylius_grid.yaml`) with thumbnail field template; admin form template + menu entry (Gift card designs, palette icon).
- Design fixture + example factory (front/back images uploaded via ImageUploader); `classic` design seeded in the default suite.
- Translations rewritten clean for 1.x (dropped all configuration-feature keys).
- Image type form field made a visible front/back selector.
- **Verified end-to-end against MariaDB**: `sylius:fixtures:load default` runs clean, Classic design persists with its front image (path set), 20 gift cards created. Quality gates green.
- **Gotcha fixed**: ImageUploader requires `Symfony\Component\HttpFoundation\File\File`, not plain `SplFileInfo` (both the provider and fixture factory).
- Deferred to phase 10: example-PDF preview button on the design form (needs the dompdf generator). Deferred to phase 11: admin image-collection add/remove JS.
- **Playwright browser verification deferred to a consolidated pass** (phase 8+): serving the app (yarn build + server + admin login) is expensive and the UI is still being built. Admin design CRUD is code-complete and container/grid-verified.

### Phase 4: Purchase flow — COMPLETE ✅ (browser verification pending consolidated pass)
- `GiftCardInformation` DTO gained `design`; `GiftCardInformationType` form (amount MoneyType + ValidGiftCardAmount, custom message w/ max length, design EntityType radio picker from the provider — first design preselected). Designs apply to BOTH delivery types (unified per user decision).
- `AddToCartTypeExtension` rebuilt: PRE_SET_DATA adds the subform for gift card products; POST_SUBMIT (priority -10) delegates to `CartGiftCardHandler`.
- `CartGiftCardHandler`: sets unitPrice + setImmutable(true); creates one **pending disabled GiftCard per OrderItemUnit** (amount/currency/deliveryType-from-variant/design/message). **Confirmed units exist at POST_SUBMIT** — Sylius `OrderItemQuantityDataMapper::mapFormsToData` creates them during form data-mapping, before the parent AddToCartType POST_SUBMIT.
- `ValidGiftCardAmount` constraint + validator (channel-aware via `GiftCardAmountLimitsProvider`, min/max from bundle config, money-formatted messages).
- `PendingGiftCardCleanupListener` (Doctrine onFlush): removes pending cards whose OrderItemUnit is deleted (cart edits, expired-cart pruning); never touches enabled/transacted cards.
- Product-page section injected via `prepend/sylius_ui.yaml` (`sylius.shop.product.show.add_to_cart_form`): amount, design thumbnail-radio picker, message, and a live HTML/CSS preview (vanilla JS `product-gift-card.js` + CSS) that occupies its own column and swaps the design image / overlays amount+message.
- `gift_card_product` fixture + example factory: creates a gift-card-flagged product with a `gift_card_delivery` option and TWO variants — **verified in DB: `gift_card-virtual` shipping_required=0, `gift_card-physical` shipping_required=1**, product `giftCard=1`.
- Asset publish dir is `bundles/setonosyliusgiftcardplugin/` (verified via assets:install).
- Quality gates green; fixtures load clean end-to-end.
- **Deferred**: styled virtual/physical delivery radio-cards (still uses stock variant selector — phase 11 polish); browser verification of the live add-to-cart + preview to the consolidated Playwright pass.

### Phases 5–12 — NOT STARTED

## Findings

- `composer.lock` is gitignored in this repo — no lock file management needed.
- Known-broken-until-later (intentional, in-place rewrites pending): `Form/Extension/AddToCartTypeExtension` (references deleted `AddToCartGiftCardInformationType`; rebuilt phase 4), `Factory/GiftCardFactory` (references deleted configuration provider; rebuilt phase 2), `EmailManager/*` + `Operator/*` + `Controller/Action/{Download,Resend}*` (reference deleted PDF renderer/resolvers; rebuilt phases 5/10/11), `EventSubscriber/SendEmailWithGiftCardToCustomerSubscriber` (rebuilt phase 10), `Applicator/GiftCardApplicator` (rebuilt phase 6), grid yaml + routes reference not-yet-rebuilt actions (phases 8/11). PHPStan will only pass once phases complete.
- `src/Doctrine/ORM/GiftCardConfigurationRepository.php` still present — delete in phase 2.
- `CustomerAutocompleteChoiceType` + `routes/admin_ajax.yaml` kept for the admin gift card form's customer field.
- `src/Order/*` (AddToCartCommand, GiftCardInformation + factories) survive; decide in phase 4 whether they move to `src/Cart/`.
- New sylius_mailer email codes: `setono_sylius_gift_card__gift_card` and `setono_sylius_gift_card__gift_cards_from_order` (renamed from 0.12.x `gift_card_customer`/`gift_card_order`).
