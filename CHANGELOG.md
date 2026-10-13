Changelog
=========

## 1.0.0

A ground-up rewrite. This is a **clean break** from `0.12.x` with no automatic data migration —
read [`UPGRADE-1.0.md`](UPGRADE-1.0.md) before upgrading a live store.

### Added
- `GiftCardDesign` resource: translatable, with front and back images, admin CRUD and an example-PDF preview.
  Customers pick a design when buying a gift card
- `delivery.capture_virtual_orders`, off by default: the authorized payment of an order that buys only virtual gift
  cards is completed as soon as it is authorized, so a shop that captures on shipment delivers them at once. Completing
  the payment pays the order, which issues and emails the cards as always; they are never issued on an authorization.
  Only for payment plugins that capture at the gateway when a payment is completed
- `GiftCardTransaction`, an append-only ledger recording every balance change, with nullable-unique
  idempotency keys so a replayed state machine transition cannot double-spend. Each row names the order it belongs
  to (the issuance of a card bought in the shop names the order that paid for it), with a copy of its number
  (`orderNumber`) that stays when the order is deleted, and, for a manual adjustment or a card issued in the admin,
  the admin who made it (`createdBy`, a copy of their user identifier). The gift card's show page lists both
- Admin **Adjust balance** action, writing a manual ledger entry with a reason and the admin who made it, which
  leads back to the card's page where the ledger is. Its form type, `AdjustGiftCardBalanceType`, requires a
  `currency` option: the card's currency, which the amount is typed in. It has no default, so an application
  reusing the form cannot leave it out and show the amount in a currency other than the card's
- The admin's gift card form takes a design, picked from thumbnails of the designs enabled in the card's channel,
  and a delivery type, virtual unless chosen otherwise. The design can be changed later; the delivery type is
  settled when the card is issued
- A status for every gift card (`GiftCardInterface::getStatus()`: usable, expired, spent, pending or disabled), shown
  in the admin gift card grid in place of the enabled column and on the card's page
- Admin gift card grid filters by customer email, channel, currency, delivery type, expired, spent and creation date,
  and sorting by customer, amount and delivery type. Pending cards stay hidden until the admin asks for them through
  the grid's *Pending gift cards* filter, which is what hides them:
  `GiftCardRepositoryInterface::createListQueryBuilder()` lists every card. Deleting is only offered for a card that may be deleted, and the gift card and design routes
  register no bulk delete
- The admin gift card page links the order the card was bought with, the orders it was applied to and its design
- The admin design grid sorts by name and lists each design's channels
- Admin outstanding-balance report, aggregating the balance of all usable gift cards per currency in SQL
- One-click **Create gift card product** admin scaffold, building the delivery option and both variants
- `bin/console setono:gift-card:create-default-design`, which creates the bundled "Classic" design and adds it to every
  channel it is not in yet, so it can be run again after a channel is added, and the `setono_gift_card_design` fixture
- A setup warning in the top bar of every admin page while gift cards are at stake and something they need is missing:
  an enabled design in a channel that sells gift cards, or the gift card payment method (see *Changed*)
- Live preview on the gift card product page — the chosen design with the amount and message overlaid,
  updating as the customer types. The amount field starts out at the gift card product's price, so the price is the
  amount the shop suggests
- What the applied gift cards cover and what remains to pay, below the order total in the cart and on every checkout
  step, and, on the thank you page of an order the gift cards pay in part, the instructions for paying the rest. The
  `setono_gift_card_covered_amount()`, `setono_gift_card_remaining_total()`, `setono_gift_card_coverage()` and
  `setono_gift_card_remaining_payment()` Twig functions give the same figures to an application's own templates
- The entity traits carry their Doctrine mapping as PHP 8 attributes as well as annotations, so they work in an
  application that maps its entities with attributes, as Sylius-Standard does
- `code_length`, `minimum_code_length`, `default_validity_period` and the purchase limits can be taken from environment
  variables
- A shop page of its own for a gift card product (`@SetonoSyliusGiftCardPlugin/shop/product/show.html.twig`), in
  place of Sylius' product page: the live preview of the card, and next to it the delivery type (when the product
  offers both), the amount, the design, the message and the quantity in Sylius' add to cart form. Sylius' image
  placeholder, reviews, price, code, variant table, empty details tab and latest products are left out. The page is
  made of blocks on the plugin's own template events (`setono_sylius_gift_card.shop.product.show.*`), so an
  application adds, moves or disables them in its `sylius_ui` configuration; Sylius' `sylius.shop.product.show.*`
  events do not fire on it
- Physical gift cards: a gift card is virtual or physical depending on the chosen variant's
  *shipping required* flag, and physical ones ship through the normal Sylius shipping flow. Fixtures can seed
  both: `delivery_type` on `setono_gift_card` (virtual when left out) and `delivery_types` on the new
  `setono_gift_card_product` fixture (a variant for each when left out). A product with a single delivery type
  gets no delivery option, so it is a simple product and the shop shows no variant choice for it
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
- Requires Sylius `1.13` and up, PHP `>= 8.1` and Symfony `^6.4` (Symfony 5.4 support dropped), with symfony/form
  `6.4.31` and up, from which a line break in a gift card message counts as one character
- Conflicts with `twig/twig` `>=3.29`. On Twig 3.29 and newer, `sylius/mailer-bundle` up to 2.2.0 fails every email
  it sends, and the release that fixes it (2.2.1) requires PHP 8.2. The conflict stays until PHP 8.1 support is
  dropped; until then, `composer require` the plugin with `-W` where Twig 3.29 or newer is locked (see the README)
- The plugin auto-configures the state machine, grids, UI events, emails, image filters and rate limiters via
  `prepend()`; host applications no longer import any bundle configuration manually
- The admin routes sit under the application's admin path (`/%sylius_admin.path_name%`, behind the admin firewall)
  instead of always under `/admin`. On the default admin path nothing moves
- The **Gift card** checkbox on the admin product form is rendered by a block on `sylius.admin.product.tab_details`, so
  the override of `@SyliusAdmin/Product/Tab/_details.html.twig` that 0.12 asked for is no longer needed
- The plugin's services are registered under their class and their interface, and use each other through their
  interfaces, so a decorator registered on an interface reaches the whole plugin, the state machine callbacks included.
  The dotted `setono_sylius_gift_card.*` service ids are gone
- The plugin's templates, routes, emails, grids, state machine callbacks and translation keys were renamed or
  rebuilt; `UPGRADE-1.0.md` lists them
- Gift cards no longer pay for gift cards: what they may pay for leaves the gift card lines out
  (`EligibleTotalCalculatorInterface`, replacing `OrderEligibleTotalProviderInterface`)
- Gift card codes are generated in a grouped, unambiguous format (the alphabet excludes `0`, `O`, `1`, `I`
  and `L`), and lookups normalise case, dashes and spaces
- A code is a bearer token, so every card is issued with a code of at least 12 significant characters: `code_length`
  (16 by default, was 20) cannot be set below 12, and the new `minimum_code_length` holds a code typed in the admin or
  given to the fixture to it too. Codes already issued keep working whatever their length
- Applying a code is rate limited, per session and per client IP. Every code that cannot be used gets the same message,
  whatever the reason, and the reason is logged with the code masked. The constraint is `GiftCardIsEligible`, with the
  options `message` and `alreadyAppliedMessage`
- The validity period is the `default_validity_period` setting, 3 years unless configured otherwise, rather than a
  setting of each gift card configuration, which was empty by default so cards never expired. A bought card's period
  counts from checkout completion rather than from when it was put in the cart
- A physical gift card is no longer emailed with its code and PDF when its order is paid, as that would make it
  spendable before it arrives: the email says it will be shipped. `delivery.email_physical_cards: true` emails them
  anyway. The admin's **Send email** always includes them, and is only offered for a card the customer can use
- `initialAmount` is set explicitly rather than seeded implicitly
- Every column the plugin maps is named explicitly, whatever naming strategy the application configures; see
  `UPGRADE-1.0.md` for the renames this can mean
- PDFs render with `dompdf` — a normal Composer dependency — replacing the wkhtmltopdf/Snappy binary, and the
  template is an ordinary overridable Twig file rather than Twig stored in the database
- Balances are committed when the order is placed and restored, once per payment, when the gift card payment is
  refunded (cancelling the order refunds it), and every mutation goes through the balance operator. The cards an order
  bought are disabled when it is cancelled or refunded in full
- The checkout checks the applied gift cards again at its last step: a card spent, disabled or changed since it was
  applied is taken off, and the customer is sent back to the cart to pay the rest by other means
- An order the gift cards pay in part stays *awaiting payment* until the rest is paid (Sylius alone would call it
  *partially paid*), so the customer can pay the rest from Sylius' shop and Sylius' unpaid order expiry cancels it.
  With a payment provider that captures later, it is *authorized* once the rest is authorized, and *partially
  authorized* until then. The plugin decorates `sylius.state_resolver.order_payment` for this. Until such an order is
  paid, its gift card payment cannot be refunded on its own: cancelling the order gives the cards their balance back
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
  fixture; until it exists the shop refuses gift cards and every admin page warns about it. It is created in no channel,
  which is all it needs: gift card payments are made with it in every channel whichever channels it is in, and in a
  channel Sylius RefundPlugin would list it as a refund destination. Its edit page in the admin explains this
- The gift card payment method's **Enabled** switch stops gift cards being redeemed: while the method is disabled the
  shop refuses gift cards as it does while the method is missing, and every admin page says so, with a link to the
  method's edit page. Gift card payments already made, and refunding them, are not affected
- Gift cards held in the currency they are bought or issued in, any currency their channel offers (#488). A customer
  browsing in another currency than the channel's base currency buys a card for the amount they type in it, which the
  card holds; its line costs that amount converted at the shop's exchange rate. A card pays converted into the
  currency of the order, judged against the order as shown in the card's currency, so a card covers an order shown as
  its balance in full, and refunding its payment gives back exactly what it took. The admin's *New gift card* form
  offers the chosen channel's currencies and starts out at its base currency (#358). Decorate
  `GiftCardCurrencyProviderInterface` to hold cards in the base currency alone; the purchase limits apply in the
  currency the amount is typed in, and `GiftCardAmountLimitsProviderInterface::getLimits()` is given it

### Removed
- **The API layer** — the whole API Platform / `sylius/api-bundle` integration. Stay on `0.12.x` if you need it
- The `GiftCardConfiguration` entity family, along with the database-stored PDF template and its live editor
- The public balance-lookup page, the shop account "my gift cards" section and the shop's PDF download
- The `origin` property on gift cards
- `OrderRepositoryTrait` and `Repository\OrderRepositoryInterface`: nothing in the plugin used their queries any
  more, so an application no longer overrides Sylius' order repository for the plugin
- The Behat and phpspec suites, replaced by PHPUnit and Playwright

### Fixed
- The add to cart form checks the stock (Sylius' `CartItemAvailability`, now mapped on `AddToCartCommandInterface`).
  0.12 mapped it on a container parameter the validator does not resolve, so the check never ran
- Applying or removing a gift card only redirects back to the `Referer` when it is on the shop's own host; 0.12 followed
  any `Referer`

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
