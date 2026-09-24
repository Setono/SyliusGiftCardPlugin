# Upgrade from `0.12.x` to `1.0`

Version `1.0` is a ground-up rewrite. It is a **clean break**: there is no automatic data migration. Read this document fully before upgrading a live store, and take a backup first.

## Requirements

- Sylius `1.13` and up (was `^1.11`) — `1.13` is the floor because the plugin uses `Sylius\Abstraction\StateMachine`, which Sylius introduced in `1.13`
- PHP `>= 8.1`, Symfony `^6.4` only (Symfony 5.4 support dropped)

## Removed: the API layer

The entire API Platform / `sylius/api-bundle` integration has been removed. If you relied on the gift card API, stay on `0.12.x` or reintroduce the endpoints in your own application.

## Feature changes

- **One gift card type.** The customer always chooses the amount. The `giftCardAmountConfigurable` product flag is gone; a product is simply a gift card product or not (`ProductTrait` now exposes a single `giftCard` flag).
- **Virtual vs physical.** New: a gift card is virtual or physical based on the chosen variant's `shipping required` flag. Physical gift cards ship through the normal Sylius shipping flow.
- **Designs.** New `GiftCardDesign` resource (translatable, with front/back images). The old `GiftCardConfiguration` / `GiftCardChannelConfiguration` / `GiftCardConfigurationImage` entities and the DB-stored Twig template are **removed** — the PDF is now a normal, overridable Twig template file.
- **Redemption is a payment, not an adjustment.** Where 0.12.x reduced the order total with a negative adjustment, a redeemed gift card is now a completed `Payment` against the order, leaving the total intact. This matches how gift cards work on other platforms and how accounting and order management systems expect to see them: selling a gift card takes money for a liability, and redeeming it settles that liability rather than discounting the order. Anything reading `order_gift_card` adjustments must read the order's gift card payments instead.
- **Ledger.** New `GiftCardTransaction` append-only ledger records every balance change; admins can adjust balances with a reason.
- **Dropped features.** The public balance-lookup page and the shop "my gift cards" account section were removed.

## Configuration migration

The bundle configuration tree changed completely. Remove any `setono_sylius_gift_card` configuration referencing `pdf_rendering`, `driver`, or the old `resources` classes, and adopt the new tree (see the README). You no longer import `@SetonoSyliusGiftCardPlugin/Resources/config/app/config.yaml` — the plugin auto-configures via `prepend()`.

Two changes deserve a closer look, because a gift card code is a bearer token (whoever knows it can spend the balance) and `1.0` guards it more strictly:

- **`code_length` must be at least 12.** `0.12.x` accepted anything from 1 and defaulted to 20. A shorter setting now stops the container from compiling with `The value 8 is too small for path "setono_sylius_gift_card.code_length". Should be greater than or equal to 12`. Raise it or drop it (the default is 16). Codes already issued keep working whatever their length; the setting only applies to codes generated from now on.
- **Applying a code is rate limited**, per session and per client IP, through two limiters the plugin registers under `framework.rate_limiter` (see the README). Behind a reverse proxy or load balancer, configure `framework.trusted_proxies` first: without it every customer's requests come from the proxy's address, so they all share one IP budget.

## Entity / schema changes

The `GiftCard` entity changed: `origin` was removed; `deliveryType`, `design`, an optimistic-lock `version` column and a `transactions` relation were added; `initialAmount` is now set explicitly; the `orderItemUnit` foreign key changed from `CASCADE` to `SET NULL`. New tables are created for `gift_card_design` (+ translation + image + channel join) and `gift_card_transaction`; the `gift_card_configuration*` tables are no longer used.

Generate a migration and review it carefully before running it against production data:

```bash
bin/console doctrine:migrations:diff
```

Existing gift cards will need `delivery_type` backfilled (default to `virtual`) and `initial_amount` populated, for example:

```sql
UPDATE setono_sylius_gift_card__gift_card SET delivery_type = 'virtual' WHERE delivery_type IS NULL OR delivery_type = '';
UPDATE setono_sylius_gift_card__gift_card SET initial_amount = amount WHERE initial_amount IS NULL OR initial_amount = 0;
```

Write a data migration for your own data as needed.

### Column names

Every column the plugin maps is now named explicitly — `initial_amount`, `currency_code`, `delivery_type`, `custom_message`, `expires_at`, and `gift_card` on `sylius_product` — so the names above are the ones you get in every application, whatever `doctrine.orm.naming_strategy` it configures.

In `0.12.x` these names were left to that strategy. If your application configures none (the Sylius-Standard default), your columns are currently `initialAmount`, `currencyCode` and `customMessage`, and `doctrine:migrations:diff` will express the change as a drop plus an add, which throws the data away. Replace those statements with renames before running the migration:

```sql
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE initialAmount initial_amount INT NOT NULL;
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE currencyCode currency_code VARCHAR(3) NOT NULL;
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE customMessage custom_message LONGTEXT DEFAULT NULL;
```

Applications that already configure an underscore naming strategy have these columns under the new names and need no rename.

## Validation overrides

The constraint that decides whether a gift card may be applied to the cart is `GiftCardIsEligible` (it was called `GiftCardIsApplicable` earlier in the `1.x` development). It gives the customer one message for every reason a card cannot be used (disabled, expired, empty, another channel or currency), so the form cannot tell someone guessing codes which ones exist, and logs the reason instead. Its only options are `message` and `alreadyAppliedMessage`: validation XML that still sets `notEnabledMessage`, `expiredMessage`, `emptyMessage`, `channelMismatchMessage` or `currencyMismatchMessage` fails with `The options "notEnabledMessage" do not exist in constraint "Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligible"`. Set `message` instead.

## Template overrides

If you overrode any of the removed templates (gift card configuration admin, the balance search page, the account gift cards section, or the cart/checkout total overrides), remove those overrides. The cart apply box and totals are now injected via `sylius_ui` events, so most host-app template overrides are no longer necessary.

## Gift card payment method

A `gift_card` payment method using the `offline` gateway is created automatically the first time it is needed. You can also create it yourself via a fixture:

```yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                payment_method:
                    options:
                        custom:
                            gift_card:
                                code: gift_card
                                name: 'Gift card'
                                gatewayFactory: offline
                                channels: ['<your-channel-code>']
```
