Changelog
=========

## 1.0.0

A ground-up rewrite. This is a **clean break** from `0.12.x` with no automatic data migration —
read [`UPGRADE-1.0.md`](UPGRADE-1.0.md) before upgrading a live store.

### Added
- `GiftCardDesign` resource: translatable, with front and back images, admin CRUD and an example-PDF preview.
  Customers pick a design when buying a gift card
- `GiftCardTransaction`, an append-only ledger recording every balance change, with nullable-unique
  idempotency keys so a replayed state machine transition cannot double-spend. Each row names the order it belongs
  to (the issuance of a card bought in the shop names the order that paid for it) and, for a manual adjustment or a
  card issued in the admin, the admin who made it (`createdBy`, a copy of their user identifier). The gift card's
  show page lists both
- Admin **Adjust balance** action, writing a manual ledger entry with a reason and the admin who made it, which
  leads back to the card's page where the ledger is
- The admin's gift card form takes a design, picked from thumbnails of the designs enabled in the card's channel,
  and a delivery type, virtual unless chosen otherwise. The design can be changed later; the delivery type is
  settled when the card is issued
- A status for every gift card (`GiftCardInterface::getStatus()`: usable, expired, spent, pending or disabled), shown
  in the admin gift card grid in place of the enabled column and on the card's page
- Admin gift card grid filters by customer email, channel, currency, delivery type, expired, spent and creation date,
  and sorting by customer and amount. Pending cards stay hidden until the admin asks for them through the grid's
  *Pending gift cards* filter, which is what hides them: `GiftCardRepositoryInterface::createListQueryBuilder()`
  lists every card. Deleting is only offered for a card that may be deleted, and the gift card and design routes
  register no bulk delete
- The admin gift card page links the order the card was bought with, the orders it was applied to and its design
- The admin design grid sorts by name and lists each design's channels
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
- Conflicts with `twig/twig` `>=3.29`. On Twig 3.29 and newer, `sylius/mailer-bundle` up to 2.2.0 fails every email
  it sends, and the release that fixes it (2.2.1) requires PHP 8.2. The conflict stays until PHP 8.1 support is
  dropped; until then, `composer require` the plugin with `-W` where Twig 3.29 or newer is locked (see the README)
- The plugin auto-configures the state machine, grids, UI events, emails and image filters via `prepend()`;
  host applications no longer import any bundle configuration manually
- Gift card codes are generated in a grouped, unambiguous format (the alphabet excludes `0`, `O`, `1`, `I`
  and `L`), and lookups normalise case, dashes and spaces
- `initialAmount` is set explicitly rather than seeded implicitly
- PDFs render with `dompdf` — a normal Composer dependency — replacing the wkhtmltopdf/Snappy binary, and the
  template is an ordinary overridable Twig file rather than Twig stored in the database
- Balances are committed when the order is placed and restored when it is cancelled or refunded, and every
  mutation goes through the balance operator
- Promotions never discount a gift card line, so a card costs and holds exactly the amount chosen: unit
  discounts skip it, an order discount is taken from the other items only, and a gift card being bought no
  longer counts towards a promotion's "item total" rule. Promotions a shop already runs behave differently:
  with "free shipping over 100.00", an order of a 100.00 physical gift card used to ship for free and now pays
  for shipping
- What the plugin creates for customers to see is named in the language of each locale of the shop instead of in
  English: the gift card payment method, the product, variants and delivery option from **Create gift card
  product** and the fixture, and the bundled default design. The order email's subject names the order
  (`email.gift_cards_from_order_subject`), and unused translation keys are gone
- The gift card payment method is no longer created the first time a customer pays with a gift card. Create it
  once with the **Create gift card payment method** button in the admin's setup warning, with
  `bin/console setono:gift-card:create-payment-method` in a deploy, or with the `setono_gift_card_payment_method`
  fixture; until it exists the shop refuses gift cards and every admin page warns about it

### Removed
- **The API layer** — the whole API Platform / `sylius/api-bundle` integration. Stay on `0.12.x` if you need it
- The `GiftCardConfiguration` entity family, along with the database-stored PDF template and its live editor
- The public balance-lookup page and the shop account "my gift cards" section
- The `origin` property on gift cards
- `OrderRepositoryTrait` and `Repository\OrderRepositoryInterface`: nothing in the plugin used their queries any
  more, so an application no longer overrides Sylius' order repository for the plugin
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
