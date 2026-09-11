Changelog
=========

## 1.0.0

A ground-up rewrite. This is a **clean break** from `0.12.x` with no automatic data migration —
read [`UPGRADE-1.0.md`](UPGRADE-1.0.md) before upgrading a live store.

### Added
- `GiftCardDesign` resource: translatable, with front and back images, admin CRUD and an example-PDF preview.
  Customers pick a design when buying a gift card
- `GiftCardTransaction`, an append-only ledger recording every balance change, with nullable-unique
  idempotency keys so a replayed state machine transition cannot double-spend
- Admin **Adjust balance** action, writing a manual ledger entry with a reason
- Admin outstanding-balance dashboard, aggregating the balance of all usable gift cards per currency in SQL
- One-click **Create gift card product** admin scaffold, building the delivery option and both variants
- Live preview on the gift card product page — the chosen design with the amount and message overlaid,
  updating as the customer types
- Physical gift cards: a gift card is virtual or physical depending on the chosen variant's
  *shipping required* flag, and physical ones ship through the normal Sylius shipping flow
- Support for both Sylius state machine adapters — winzou callbacks and the equivalent Symfony Workflow
  subscribers are both registered, so the plugin behaves the same whichever adapter is configured
- A Playwright suite covering the admin and shop UI

### Changed
- **Redeeming a gift card creates a `Payment` against the order instead of a negative adjustment.** The order
  total stays intact: selling a gift card takes money for a liability, and redeeming it settles that liability
  rather than discounting the order. Anything reading `order_gift_card` adjustments must read the order's gift
  card payments instead
- The customer always chooses the amount, within a configurable minimum and maximum. The
  `giftCardAmountConfigurable` product flag is gone — a product is a gift card product or it is not
- Requires Sylius `1.13` and up, PHP `>= 8.1` and Symfony `^6.4` (Symfony 5.4 support dropped)
- The plugin auto-configures the state machine, grids, UI events, emails and image filters via `prepend()`;
  host applications no longer import any bundle configuration manually
- Gift card codes are generated in a grouped, unambiguous format (the alphabet excludes `0`, `O`, `1`, `I`
  and `L`), and lookups normalise case, dashes and spaces
- `initialAmount` is set explicitly rather than seeded implicitly
- PDFs render with `dompdf` — a normal Composer dependency — replacing the wkhtmltopdf/Snappy binary, and the
  template is an ordinary overridable Twig file rather than Twig stored in the database
- Balances are committed when the order is placed and restored when it is cancelled or refunded, and every
  mutation goes through the balance operator

### Removed
- **The API layer** — the whole API Platform / `sylius/api-bundle` integration. Stay on `0.12.x` if you need it
- The `GiftCardConfiguration` entity family, along with the database-stored PDF template and its live editor
- The public balance-lookup page and the shop account "my gift cards" section
- The `origin` property on gift cards
- The Behat and phpspec suites, replaced by PHPUnit and Playwright

## 0.6.0

### Added
- Customer relation on gift card
- API endpoints for gift card resource

## 0.5.0

- Fixture `setono_gift_card` now extended from `AbstractResourceFixture`,
  so use something like
  
  ```yaml
    setono_gift_card:
        options:
            random: 20
  ```
  
  rather than
  
  ```yaml
    setono_gift_card:
        options:
            amount: 20
  ```
  
  to get random 20 gift cards.
