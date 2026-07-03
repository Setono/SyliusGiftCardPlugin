# Upgrade from `0.12.x` to `1.0`

Version `1.0` is a ground-up rewrite. It is a **clean break**: there is no automatic data migration. Read this document fully before upgrading a live store, and take a backup first.

## Requirements

- Sylius `1.13` or `1.14` (was `^1.11`)
- PHP `>= 8.1`, Symfony `^6.4` only (Symfony 5.4 support dropped)

## Removed: the API layer

The entire API Platform / `sylius/api-bundle` integration has been removed. If you relied on the gift card API, stay on `0.12.x` or reintroduce the endpoints in your own application.

## Feature changes

- **One gift card type.** The customer always chooses the amount. The `giftCardAmountConfigurable` product flag is gone; a product is simply a gift card product or not (`ProductTrait` now exposes a single `giftCard` flag).
- **Virtual vs physical.** New: a gift card is virtual or physical based on the chosen variant's `shipping required` flag. Physical gift cards ship through the normal Sylius shipping flow.
- **Designs.** New `GiftCardDesign` resource (translatable, with front/back images). The old `GiftCardConfiguration` / `GiftCardChannelConfiguration` / `GiftCardConfigurationImage` entities and the DB-stored Twig template are **removed** — the PDF is now a normal, overridable Twig template file.
- **Redemption modes.** New `setono_sylius_gift_card.redemption.mode` config: `adjustment` (like 0.12.x) or `payment` (real payment entities).
- **Ledger.** New `GiftCardTransaction` append-only ledger records every balance change; admins can adjust balances with a reason.
- **Dropped features.** The public balance-lookup page and the shop "my gift cards" account section were removed.

## Configuration migration

The bundle configuration tree changed completely. Remove any `setono_sylius_gift_card` configuration referencing `pdf_rendering`, `driver`, or the old `resources` classes, and adopt the new tree (see the README). You no longer import `@SetonoSyliusGiftCardPlugin/Resources/config/app/config.yaml` — the plugin auto-configures via `prepend()`.

## Entity / schema changes

The `GiftCard` entity changed: `origin` was removed; `deliveryType`, `design`, an optimistic-lock `version` column and a `transactions` relation were added; `initialAmount` is now set explicitly; the `orderItemUnit` foreign key changed from `CASCADE` to `SET NULL`. New tables are created for `gift_card_design` (+ translation + image + channel join) and `gift_card_transaction`; the `gift_card_configuration*` tables are no longer used.

Generate a migration and review it carefully before running it against production data:

```bash
bin/console doctrine:migrations:diff
```

Existing gift cards will need `delivery_type` backfilled (default to `virtual`) and `initial_amount` populated. Write a data migration for your own data as needed.

## Template overrides

If you overrode any of the removed templates (gift card configuration admin, the balance search page, the account gift cards section, or the cart/checkout total overrides), remove those overrides. The cart apply box and totals are now injected via `sylius_ui` events, so most host-app template overrides are no longer necessary.

## Payment mode setup (optional)

If you enable `redemption.mode: payment`, a `gift_card` payment method using the `offline` gateway is created automatically the first time it is needed. You can also create it yourself via a fixture:

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
