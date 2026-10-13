# Upgrade from `0.12.x` to `1.0`

Version `1.0` is a ground-up rewrite. It is a **clean break**: there is no automatic data migration. Read this document fully before upgrading a live store, and take a backup first. [Before you upgrade](#before-you-upgrade) lists what costs money when it is missed.

## Requirements

- Sylius `^1.13`, i.e. 1.13 and 1.14 (was `^1.11`) — `1.13` is the floor because the plugin uses `Sylius\Abstraction\StateMachine`, which Sylius introduced in `1.13`
- PHP `>= 8.1`, Symfony `^6.4` only (Symfony 5.4 support dropped), with symfony/form `6.4.31` and up
- Twig below `3.29`. The plugin conflicts with `twig/twig` `>=3.29`, because `sylius/mailer-bundle` up to 2.2.0 cannot send emails on newer Twig releases, and the fixed 2.2.1 needs PHP 8.2. If your application has Twig 3.29 or newer locked, update with `composer require setono/sylius-gift-card-plugin:^1.0 --with-all-dependencies` so Composer can move Twig back to 3.28. The README's [Twig below 3.29](README.md#twig-below-329) section explains it in full
- PDFs render with `dompdf/dompdf` instead of wkhtmltopdf through `knplabs/knp-snappy-bundle`. If nothing else in your application uses KnpSnappyBundle, remove it from `config/bundles.php` along with its configuration, and wkhtmltopdf from your servers

## Before you upgrade

`0.12.x` paid an order with a gift card through a negative `order_gift_card` adjustment, and took the balance off the card when the order was placed. `1.0` pays with a gift card through a payment and knows nothing of those adjustments, so the orders in progress when you upgrade need attention.

**Open carts.** `0.12.x` had Sylius remove the `order_gift_card` adjustments every time it recalculated a cart, and added them again. `1.0` does neither: a cart that has a gift card applied when you upgrade keeps its adjustment for good, while the card is still applied to it, so when the order is placed the card pays as a payment as well, and the customer gets the amount off twice. Upgrade while no customer is checking out, and remove the adjustments from carts, together with what they took off their totals (MySQL syntax):

```sql
UPDATE sylius_order o
JOIN (SELECT order_id, SUM(amount) AS gift_cards FROM sylius_adjustment WHERE type = 'order_gift_card' GROUP BY order_id) a ON a.order_id = o.id
SET o.adjustments_total = o.adjustments_total - a.gift_cards, o.total = o.total - a.gift_cards
WHERE o.state = 'cart';

DELETE a FROM sylius_adjustment a JOIN sylius_order o ON o.id = a.order_id WHERE a.type = 'order_gift_card' AND o.state = 'cart';
```

The cards stay applied to the carts, and pay as payments once the orders are placed.

**Placed orders.** An order placed under `0.12.x` keeps its adjustment, which is right, as it is what the customer paid with. But `1.0` gives a card its balance back by refunding its gift card payment (cancelling an order refunds them), and such an order has none: cancelling or refunding it under `1.0` gives the card nothing back. That includes the unpaid orders `sylius:cancel-unpaid-orders` cancels after the upgrade. Settle the unpaid orders that were paid in part with a gift card before upgrading, or give their cards the balance back with **Adjust balance** when you cancel them.

**The gift card configurations.** The `setono_sylius_gift_card__configuration`, `__channel_configuration` and `__configuration_image` tables hold your PDF templates, validity periods, page sizes and background images. `1.0` has no use for them and `doctrine:migrations:diff` drops them. Write down what you need before you migrate (see [Configuration migration](#configuration-migration) and the designs under [Feature changes](#feature-changes)).

## Removed: the API layer

The entire API Platform / `sylius/api-bundle` integration has been removed. If you relied on the gift card API, stay on `0.12.x` or reintroduce the endpoints in your own application.

The `0.12.x` README had you copy Sylius' `Order.xml` API resource and set the input of `shop_add_item` to `Setono\SyliusGiftCardPlugin\Api\Command\AddItemToCart`. That class is gone: set it back to Sylius' `Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart`, or remove the copy if you made no other change to it.

## Feature changes

- **One gift card type.** The customer always chooses the amount. The `giftCardAmountConfigurable` product flag is gone; a product is simply a gift card product or not (`ProductTrait` now exposes a single `giftCard` flag). A product with one variant per fixed amount now offers those variants where the gift card product page offers the delivery types, and the customer types any amount: rebuild it with a variant per delivery type, or create a new one with **Create gift card product**.
- **Virtual vs physical.** New: a gift card is virtual or physical based on the chosen variant's `shipping required` flag. Physical gift cards ship through the normal Sylius shipping flow, and the email sent when their order is paid leaves out the code and the PDF (see `delivery.email_physical_cards` in the README). Sylius ticks `shipping required` on every variant unless it is unticked, so **untick it on the variants of your gift card products that are virtual**, or customers buy physical cards. The `setono_gift_card` fixture seeds virtual cards, unless an entry says `delivery_type: physical`.
- **Designs.** New `GiftCardDesign` resource (translatable, with front/back images). The old `GiftCardConfiguration` / `GiftCardChannelConfiguration` / `GiftCardConfigurationImage` entities and the DB-stored Twig template are **removed** — the PDF is now a normal, overridable Twig template file. No design is created from your configurations' background images: run `bin/console setono:gift-card:create-default-design` for the bundled one, and create your own in the admin. Until a channel that sells gift cards has an enabled design, the admin warns about it and the PDF renders a framed default.
- **Redemption is a payment, not an adjustment.** Where 0.12.x reduced the order total with a negative adjustment, a redeemed gift card is now a completed `Payment` against the order, leaving the total intact. This matches how gift cards work on other platforms and how accounting and order management systems expect to see them: selling a gift card takes money for a liability, and redeeming it settles that liability rather than discounting the order. Anything reading `order_gift_card` adjustments must read the order's gift card payments instead. An order the gift cards pay only in part stays *awaiting payment* until the rest is paid, or is *authorized* once a payment provider that captures later authorizes the rest; the plugin decorates `sylius.state_resolver.order_payment` for it.
- **What gift cards pay for.** Gift cards no longer pay for gift cards: `0.12.x` let a card pay the whole order total, `1.0` leaves the gift card lines out (`EligibleTotalCalculatorInterface`, which replaces `OrderEligibleTotalProviderInterface`).
- **Promotions.** Promotions no longer discount gift card lines, and a gift card being bought no longer counts towards a promotion's "item total" rule, so promotions you already run behave differently: with "free shipping over 100.00", an order of a 100.00 physical gift card used to ship for free and now pays for shipping. `OrderTrait` now overrides `getPromotionSubjectTotal()` and `getNonDiscountedItemsTotal()`; if your `Order` overrides either, or uses another trait that does, build on the plugin's version.
- **Ledger.** New `GiftCardTransaction` append-only ledger records every balance change; admins can adjust balances with a reason. A row names the admin who adjusted the balance or issued the card in the admin, and the issuance of a card bought in the shop names the order that paid for it.
- **The gift card product page.** The shop shows a gift card product on the plugin's own page instead of Sylius' product page, so your override of `@SyliusShop/Product/show.html.twig`, and blocks you put on the `sylius.shop.product.show.*` events, no longer apply to gift card products (see the README's *The gift card product page*).
- **Admin.** The gift card list has one status per card instead of the enabled column, **Resend email** is now **Send email** and only offered for a card the customer can use, and there is no bulk delete.
- **Dropped features.** The public balance-lookup page, the shop "my gift cards" account section and the shop's PDF download were removed.

## Configuration migration

The bundle configuration tree changed completely. Remove the `pdf_rendering` and `driver` keys and the `gift_card_configuration`, `gift_card_configuration_image` and `gift_card_channel_configuration` resources from your `setono_sylius_gift_card` configuration (`resources.gift_card` is still there), and adopt the new tree (see the README). Remove the import of `@SetonoSyliusGiftCardPlugin/Resources/config/app/config.yaml`, too: the file no longer exists, and the plugin auto-configures via `prepend()`.

What your gift card configurations held in the database is configuration now, one setting for the whole shop:

- **The validity period** is `default_validity_period`, which is `'3 years'` unless you set it. In `0.12.x` it was a field of each configuration, empty unless you filled it in, so your cards may never have expired. To keep that, set `default_validity_period: ~`; otherwise set the period you used. The setting applies to the cards bought or issued from then on, and a bought card's period counts from checkout completion. Cards that already exist keep their expiry.
- **The page size** is `pdf.page_size` (A6 by default). The card is always landscape, so the orientation is gone.
- **The template** is `@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig`, which you override like any template (see the README's *Customizing the PDF*).

Two changes deserve a closer look, because a gift card code is a bearer token (whoever knows it can spend the balance) and `1.0` guards it more strictly:

- **`code_length` must be at least 12.** `0.12.x` accepted anything from 1 and defaulted to 20. A shorter setting now stops the container from compiling with `The value 8 is too small for path "setono_sylius_gift_card.code_length". Should be greater than or equal to 12`. Raise it or drop it (the default is 16). Codes already issued keep working whatever their length; the setting only applies to codes generated from now on. The same floor applies to a code an admin types when issuing a card and to codes given to the `setono_gift_card` fixture, through the new `minimum_code_length` setting (12 by default, and it cannot be lowered). Both are counted once normalized, the way they are saved and the cart looks them up: in capitals, with everything but the letters A to Z and the digits dropped. A fixture file naming a code shorter than that now fails to load, and so does one with two entries whose codes are the same once normalized.
- **Applying a code is rate limited**, per session and per client IP, through two limiters the plugin registers under `framework.rate_limiter` (see the README). Behind a reverse proxy or load balancer, configure `framework.trusted_proxies` first: without it every customer's requests come from the proxy's address, so they all share one IP budget.

## Routing

`@SetonoSyliusGiftCardPlugin/Resources/config/routes.yaml` now puts the admin routes under your admin path (`/%sylius_admin.path_name%`, which `SYLIUS_ADMIN_ROUTING_PATH_NAME` sets), where `0.12.x` always put them under `/admin`. So does `@SetonoSyliusGiftCardPlugin/Resources/config/routes_no_locale.yaml`, which a shop whose URLs carry no locale keeps importing instead, and which still puts the shop routes at the root of the shop. On the default admin path nothing moves.

If your admin lives somewhere else, you will find the plugin's admin pages under your admin path, whichever of the two files you import: for an admin at `/backoffice`, `/admin/gift-cards/...` becomes `/backoffice/gift-cards/...` and `/admin/ajax/customer/...` becomes `/backoffice/ajax/customer/...`. Update any link or bookmark to the old addresses. Those were outside the admin firewall, where the customer search answered anyone and a signed in shop customer could download the PDF, with its code, of any card without a customer; under your admin path they sit behind it with the rest of the admin.

If you imported the plugin's route files yourself, to get the admin pages under your admin path or because `routes_no_locale.yaml` was missing from `1.x` development versions for a while, you can go back to importing `routes.yaml` or `routes_no_locale.yaml`.

Routes that were renamed or removed, for your links, templates and route overrides:

- Shop: `setono_sylius_gift_card_shop_partial_add_gift_card_to_order` and `setono_sylius_gift_card_shop_ajax_add_gift_card_to_order` are replaced by `setono_sylius_gift_card_shop_add_gift_card_to_order` (`POST /gift-cards`), whose form the plugin's block renders on the cart page. `setono_sylius_gift_card_shop_remove_gift_card_from_order` takes a `POST` with a CSRF token instead of a `GET`, at `/gift-cards/{giftCard}/remove`. `setono_sylius_gift_card_shop_search_gift_card`, `setono_sylius_gift_card_shop_gift_card_index` and `setono_sylius_gift_card_shop_gift_card_pdf` are removed. The `0.12.x` README showed how to choose where the customer lands after applying or removing a card by redefining the route with a `redirect` default and `_controller: setono_sylius_gift_card.controller.action.*`. The `redirect` default still works, but those services are gone: copy the route from `@SetonoSyliusGiftCardPlugin/Resources/config/routes/shop.yaml` again, whose `_controller` is the action's class
- Admin: `setono_sylius_gift_card_admin_gift_card_resend_email` (`GET`) is now `setono_sylius_gift_card_admin_gift_card_send_email` (`POST`). `setono_sylius_gift_card_admin_gift_card_create` no longer takes a `{channelCode}` (the channel is a field of the form), and `setono_sylius_gift_card_admin_gift_card_create_for_channel`, `setono_sylius_gift_card_admin_gift_card_orders_index`, the bulk delete, every `setono_sylius_gift_card_admin_gift_card_configuration*` route, `setono_sylius_gift_card_admin_ajax_generate_pdf` and the admin API routes are removed

## Entity / schema changes

The `GiftCard` entity changed: `origin` was removed; `deliveryType`, `design`, an optimistic-lock `version` column and a `transactions` relation were added; `initialAmount` is now set explicitly; the `orderItemUnit` foreign key changed from `CASCADE` to `SET NULL`. New tables are created for `gift_card_design` (+ translation + image + channel join) and `gift_card_transaction`; the `setono_sylius_gift_card__configuration`, `__channel_configuration` and `__configuration_image` tables are dropped (see [Before you upgrade](#before-you-upgrade)).

Generate a migration and review it carefully before running it against production data:

```bash
bin/console doctrine:migrations:diff
```

### Column names

Every column the plugin maps is now named explicitly — `initial_amount`, `currency_code`, `custom_message`, `expires_at` and the new `delivery_type`, and `gift_card` on `sylius_product` — so these are the names you get in every application, whatever `doctrine.orm.naming_strategy` it configures.

In `0.12.x` these names were left to that strategy. If your application configures none (the Sylius-Standard default), your columns are currently `initialAmount`, `currencyCode`, `customMessage`, `expiresAt`, and `giftCard` on `sylius_product`. Doctrine renames a column when it can tell which old column the new one replaces, and otherwise drops the old one and adds the new one, which throws its data away. That is what happens to the product flag: `giftCard` and `giftCardAmountConfigurable` look alike, so the diff drops `giftCard` and adds `gift_card`, and every gift card product loses its flag. Check the diff for each of these columns, and turn any drop plus add into a rename (MySQL syntax):

```sql
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE initialAmount initial_amount INT NOT NULL;
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE currencyCode currency_code VARCHAR(3) NOT NULL;
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE customMessage custom_message LONGTEXT DEFAULT NULL;
ALTER TABLE setono_sylius_gift_card__gift_card CHANGE expiresAt expires_at DATETIME DEFAULT NULL;
ALTER TABLE sylius_product CHANGE giftCard gift_card TINYINT(1) DEFAULT 0 NOT NULL;
```

Keep the diff's drops of `giftCardAmountConfigurable` and `origin`. Applications that already configure an underscore naming strategy have these columns under the new names and need no rename.

### Existing gift cards

Existing gift cards need `delivery_type` backfilled; `virtual` is what every `0.12.x` card was:

```sql
UPDATE setono_sylius_gift_card__gift_card SET delivery_type = 'virtual' WHERE delivery_type IS NULL OR delivery_type = '';
```

On a database that refuses to add a `NOT NULL` column without a default to a table with rows (PostgreSQL, say), add `delivery_type` with `DEFAULT 'virtual'` in the migration instead, and drop the default afterwards.

`0.12.x` already recorded each card's initial amount when it was created. Only a card with an initial amount of `0`, from before the column existed, needs one; its current balance is the best you have, though it is not what the card started with:

```sql
UPDATE setono_sylius_gift_card__gift_card SET initial_amount = amount WHERE initial_amount = 0;
```

**Codes** are looked up in capitals, with anything but letters and digits left out, the way a customer's typing is normalized. `0.12.x` generated codes that way, but stored a code an admin typed as it was typed, so such a code in lowercase or with dashes or spaces can no longer be found. Store those normalized, after checking that no two codes then collide (the unique index refuses the update if they do). This takes care of case, dashes and spaces; look for any other character yourself:

```sql
UPDATE setono_sylius_gift_card__gift_card SET code = UPPER(REPLACE(REPLACE(code, '-', ''), ' ', ''));
```

**The ledger** table (see below) is new, so the cards bought on `0.12.x` have no issuance on it, while every card `1.0` issues for an order has one. Without it, `1.0` cannot tell a card a customer paid for from one issued in the admin or still waiting for its order to be paid. Once its order is deleted (the order item unit is now set to `NULL` rather than deleting the card), it can be deleted. Once it is disabled, it reads as pending, so the grid hides it and offers to delete it. Give every card bought on an order that was paid the issuance `1.0` would have written, naming the order, after `initial_amount` is populated and the codes are normalized, as the issuance's idempotency key carries the code. On MySQL and MariaDB, where the ledger's `id` is `AUTO_INCREMENT`:

```sql
INSERT INTO setono_sylius_gift_card__gift_card_transaction (gift_card_id, order_id, order_number, amount, type, idempotency_key, created_at)
SELECT c.id, o.id, o.number, c.initial_amount, 'issue', CONCAT('issue-', c.code), c.created_at
FROM setono_sylius_gift_card__gift_card c
JOIN sylius_order_item_unit u ON u.id = c.order_item_unit_id
JOIN sylius_order_item i ON i.id = u.order_item_id
JOIN sylius_order o ON o.id = i.order_id
WHERE o.payment_state IN ('paid', 'partially_refunded', 'refunded')
AND NOT EXISTS (SELECT 1 FROM setono_sylius_gift_card__gift_card_transaction t WHERE t.gift_card_id = c.id);
```

On PostgreSQL, Doctrine ORM 2 gives the `id` a sequence rather than a default, so the statement takes each id from it. That also moves the sequence along, so the next ledger row the plugin writes does not collide with one of these. An application that has PostgreSQL use identity columns instead (Doctrine's `identity_generation_preferences`) runs the statement above as it is.

```sql
INSERT INTO setono_sylius_gift_card__gift_card_transaction (id, gift_card_id, order_id, order_number, amount, type, idempotency_key, created_at)
SELECT nextval('setono_sylius_gift_card__gift_card_transaction_id_seq'), c.id, o.id, o.number, c.initial_amount, 'issue', CONCAT('issue-', c.code), c.created_at
FROM setono_sylius_gift_card__gift_card c
JOIN sylius_order_item_unit u ON u.id = c.order_item_unit_id
JOIN sylius_order_item i ON i.id = u.order_item_id
JOIN sylius_order o ON o.id = i.order_id
WHERE o.payment_state IN ('paid', 'partially_refunded', 'refunded')
AND NOT EXISTS (SELECT 1 FROM setono_sylius_gift_card__gift_card_transaction t WHERE t.gift_card_id = c.id);
```

It reaches every card bought on `0.12.x`, since `0.12.x` deleted a card along with its order item unit, so a card that still exists still has its order. The cards of an order that was never paid are left alone: `1.0` only issues a card once its order is paid, and until then the card is pending. The issuance holds `initial_amount`, the amount the card was bought for. `0.12.x` kept no ledger of what was spent from a card, so on a card that was spent from, the ledger does not add up to its balance. Nothing in `1.0` adds the ledger up, but keep it in mind when reading one.

**Currencies.** `0.12.x` let an admin issue a card in any currency of the shop, and spent its balance in the order's currency as it was. `1.0` refuses a card in a currency its channel does not offer, and converts one in another currency the channel offers (see [Currencies](#currencies)). Check the cards whose `currency_code` is not their channel's base currency.

**Messages** longer than `purchase.maximum_message_length` (200 characters by default) are kept, but the admin's edit form refuses to save such a card until the message is shortened or the setting raised.

Write a data migration for your own data as needed.

### The ledger table

`setono_sylius_gift_card__gift_card_transaction` has the columns `amount`, `type`, `reason`, `idempotency_key` (nullable, unique), `created_by` (nullable), `order_number` (nullable), `created_at`, and the foreign keys `gift_card_id` (`CASCADE` on delete), `order_id` and `payment_id` (both `SET NULL` on delete).

`created_by` holds the user identifier of the admin who adjusted a balance or issued a card in the admin. It is a copy of the identifier as text, not a foreign key to `sylius_admin_user`, so the ledger keeps naming the admin after their account is renamed or deleted. An application that created this table from a `1.x` development version before `created_by` existed gets this from `doctrine:migrations:diff`:

```sql
ALTER TABLE setono_sylius_gift_card__gift_card_transaction ADD created_by VARCHAR(255) DEFAULT NULL;
```

The rows such an application already has keep `created_by` `NULL`, and the issuance rows of cards bought in the shop before then have no `order_id`. The card's order item still leads to that order.

`order_number` holds the number of the order a row belongs to, copied when the row is written, so the ledger keeps naming the order once `order_id` is set to `NULL` because the order was deleted. It is also what keeps a card a customer paid for from becoming deletable then: an issuance that names an order is that of a bought card. An application that created this table from a `1.x` development version before `order_number` existed gets the column from `doctrine:migrations:diff`. It then fills the column for the rows it already has from the orders that still exist. The first `UPDATE` takes the order a row names. The second fills the issuance of a card bought before `order_id` was recorded (see above), `order_id` included, from the order the card's order item unit leads to:

```sql
ALTER TABLE setono_sylius_gift_card__gift_card_transaction ADD order_number VARCHAR(255) DEFAULT NULL;
UPDATE setono_sylius_gift_card__gift_card_transaction SET order_number = (SELECT number FROM sylius_order WHERE sylius_order.id = setono_sylius_gift_card__gift_card_transaction.order_id) WHERE order_id IS NOT NULL;
UPDATE setono_sylius_gift_card__gift_card_transaction
SET order_id = (SELECT i.order_id FROM setono_sylius_gift_card__gift_card c JOIN sylius_order_item_unit u ON u.id = c.order_item_unit_id JOIN sylius_order_item i ON i.id = u.order_item_id WHERE c.id = setono_sylius_gift_card__gift_card_transaction.gift_card_id),
    order_number = (SELECT o.number FROM setono_sylius_gift_card__gift_card c JOIN sylius_order_item_unit u ON u.id = c.order_item_unit_id JOIN sylius_order_item i ON i.id = u.order_item_id JOIN sylius_order o ON o.id = i.order_id WHERE c.id = setono_sylius_gift_card__gift_card_transaction.gift_card_id)
WHERE type = 'issue' AND order_id IS NULL AND order_number IS NULL;
```

The issuances of cards issued in the admin or by the fixtures have no unit, and stay without an order. A row whose order was deleted before then has no number to copy.

## Repository overrides

`OrderRepositoryTrait` and `Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface` were removed: nothing in the plugin uses their queries any more. Take both off your order repository; if that leaves it empty, delete it along with its `repository` line under `sylius_order.resources.order.classes`.

Keep `CustomerRepositoryTrait` and `Setono\SyliusGiftCardPlugin\Repository\CustomerRepositoryInterface` on your customer repository: the customer field on the admin's gift card form searches customers with it (see the README).

## Validation overrides

The constraints of `0.12.x` are gone: `GiftCardIsNotExpired`, `DatePeriod`, `HasBackgroundImage`, `DefaultGiftCardConfigurationMustExist`, and `Pdf\ValidOrientation` and `Pdf\ValidPageSize`. Validation XML naming any of them fails; remove it.

The constraint that decides whether a gift card may be applied to the cart is now `GiftCardIsEligible` (it was called `GiftCardIsApplicable` earlier in the `1.x` development). It gives the customer one message for every reason a card cannot be used (disabled, expired, empty, another channel or currency), so the form cannot tell someone guessing codes which ones exist, and logs the reason instead. Besides Symfony's own, its only options are `message` and `alreadyAppliedMessage`: validation XML that still sets `notEnabledMessage`, `expiredMessage`, `emptyMessage`, `channelMismatchMessage` or `currencyMismatchMessage` fails with `The options "notEnabledMessage" do not exist in constraint "Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligible".` Set `message` instead.

The add-to-cart form checks the stock, counting what the cart already holds of the variant, the way Sylius' own form does (`CartItemAvailability`, now mapped on `AddToCartCommandInterface`). `0.12.x` mapped it on `%setono_sylius_gift_card.order.model.add_to_cart_command.class%`, a container parameter the validator does not resolve, so the check never ran. If your application mapped `CartItemAvailability` on its add-to-cart command itself to make up for that, remove that mapping, or a lack of stock is reported twice.

The validation groups of the plugin's forms are parameters named after the form's class, e.g. `Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType.validation_groups` instead of `setono_sylius_gift_card.form.type.gift_card.validation_groups`.

## Template overrides

The `0.12.x` README had you copy templates from its test application. Remove those copies: they refer to what no longer exists, so they break pages rather than merely being unnecessary. That is every override that reads the `AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT` constant (the shop's `Cart/summary.html.twig`, `Cart/Summary/_totals.html.twig`, `Checkout/_summary.html.twig` and `Common/Order/Table/_totals.html.twig`, and the admin's `Order/Show/Summary/_totals.html.twig`), includes `@SetonoSyliusGiftCardPlugin/Cart/_giftCard.html.twig` (the shop's `Cart/Summary/_items.html.twig`), or links to a removed route (the shop's `layout.html.twig`, whose link to the balance search breaks every shop page). The admin's `Form/imagesTheme.html.twig` only styled the images of the gift card configurations, which are gone. The cart apply box and totals are now injected via `sylius_ui` events, so most host-app template overrides are no longer necessary.

That includes the override of `@SyliusAdmin/Product/Tab/_details.html.twig` that `0.12.x` asked for: the "Gift card" checkbox is now rendered by the plugin's `setono_gift_card` block on the `sylius.admin.product.tab_details` event, and the `giftCardAmountConfigurable` field the override rendered no longer exists, so a `form_row(form.giftCardAmountConfigurable)` left in it breaks the product form. Remove the override, or at least that line.

The plugin's own templates moved and were renamed: the `Admin/`, `Shop/`, `Cart/` and `Email/` directories and their camel case names became `admin/`, `shop/` and `email/` with snake case names (`Email/giftCardsFromOrder.html.twig` is now `email/gift_cards_from_order.html.twig`, say). An override under `templates/bundles/SetonoSyliusGiftCardPlugin/` keeps its old path and no longer applies: compare it with the new template and move it. The block on `sylius.shop.product.show.add_to_cart_form` is now called `setono_gift_card_information` (was `setono_sylius_gift_card_information`), and the template events `setono_sylius_gift_card.shop.cart.summary.applied_gift_cards` and `setono_sylius_gift_card.shop.account.gift_card.index.*` are gone. The `setono_sylius_gift_card_background` image filter is gone too.

## Emails

The emails were renamed: `gift_card_customer` is now `setono_sylius_gift_card__gift_card` (template `email/gift_card.html.twig`) and `gift_card_order` is `setono_sylius_gift_card__gift_cards_from_order` (template `email/gift_cards_from_order.html.twig`, subject `setono_sylius_gift_card.email.gift_cards_from_order_subject`). Configuration under `sylius_mailer.emails` for the old names, a sender or `enabled: false` say, no longer applies: move it to the new names.

## Services and classes

The plugin's services are registered under their class and their interface, and the dotted `0.12.x` ids (`setono_sylius_gift_card.applicator.gift_card`, `setono_sylius_gift_card.resolver.redirect_url`, `setono_sylius_gift_card.generator.gift_card_code` and the rest) are gone. Decorate or replace the interface instead; the plugin uses its services through their interfaces, so what you register there is what it uses everywhere.

Several extension points were renamed or replaced:

- `Provider\OrderEligibleTotalProviderInterface::getEligibleTotal($order, $giftCard)` is `Calculator\EligibleTotalCalculatorInterface::getEligibleTotal($order)`
- `Renderer\PdfRendererInterface` is `Pdf\GiftCardPdfGeneratorInterface`
- `EmailManager\GiftCardEmailManagerInterface` is `Mailer\GiftCardEmailManagerInterface`
- `Modifier\OrderGiftCardAmountModifierInterface` is replaced by `Operator\GiftCardBalanceOperatorInterface`, through which every balance change goes

`Model\AdjustmentInterface`, `Security\GiftCardVoter`, the configuration, PDF rendering option and template providers, the locale and customer channel resolvers, the `setono_sylius_gift_card_string` grid field type and the `ssgc_get_base64_encoded_example_pdf_content` Twig function are removed. The model, repository and factory interfaces changed with the features (`GiftCardInterface` lost the origin and its `ORIGIN_*` constants, `GiftCardFactoryInterface` its order item unit and example methods, `GiftCardRepositoryInterface` `findEnabled()` and `createAccountListQueryBuilder()`), so a class of your own implementing one of them needs revisiting against the new interface.

## State machine callbacks

The callbacks are named `setono_sylius_gift_card__*` (`setono_sylius_gift_card__commit_redemption`, `setono_sylius_gift_card__enable_gift_cards` and so on) instead of `setono_decrement_gift_card_amount`, `setono_increment_gift_card_amount`, `setono_order_cancellation_gift_card`, `setono_gift_card_associate_to_customer`, `setono_gift_card_enable` and `setono_gift_card_send`. Configuration that disables or changes a callback by its old name no longer applies. The plugin also registers the equivalent Symfony Workflow subscribers, for applications running Sylius' graphs on Symfony Workflow.

## Grids

The `setono_sylius_gift_card_admin_gift_card_configuration`, `setono_sylius_gift_card_admin_gift_card_order` and `setono_sylius_gift_card_shop_gift_card` grids are removed. `setono_sylius_gift_card_admin_gift_card` was rebuilt: grid configuration of your own that changes its fields, filters or actions needs checking against the new one.

## Fixtures

The `setono_gift_card_configuration` fixture is removed; `setono_gift_card_design`, `setono_gift_card_product` and `setono_gift_card_payment_method` are new. The suite in `@SetonoSyliusGiftCardPlugin/Resources/config/app/fixtures.yaml` was rewritten too: its `setono_gift_card_channel_specific` and `setono_gift_card_custom` entries are gone, so a suite of yours that changed them changes nothing.

## Translations

The translation keys of what was removed are gone, along with some that were renamed (`setono_sylius_gift_card.email.your_gift_cards_you_bought_in_the_order` is `setono_sylius_gift_card.email.gift_cards_from_order_subject`, say). Check your translation overrides of the plugin's keys against `src/Resources/translations`.

## Currencies

A gift card is held in the currency it was bought or issued in, which can be any currency its channel offers, and pays converted into the order's currency, which is the channel's base currency, at the exchange rates kept in the admin (*Configuration › Exchange rates*). A customer browsing in another currency than the base currency buys a card for the amount they type in that currency. Keep an exchange rate for every currency a channel offers: Sylius converts between two currencies it has no rate for one to one. To hold every card in its channel's base currency instead, decorate `GiftCardCurrencyProviderInterface` (see the README's *Holding gift cards in the base currency only*).

## Gift card payment method

A redeemed gift card becomes a payment made with an offline payment method with the code `gift_card` (the `redemption.payment_method_code` setting). It is not created automatically: create it once after upgrading, either with the **Create gift card payment method** button in the warning the admin shows while it is missing (on the gift card, design and payment method indexes), or by running the command below in your deploy. Both create the method in no channel, which is all it needs: gift card payments are made with it in every channel, whichever channels it is in. Both leave an existing method alone, channels included, so they are safe on a shop that already has it.

```bash
bin/console setono:gift-card:create-payment-method
```

Until the method exists the shop refuses gift cards, and the admin warns on every page. It does the same while the method is disabled: its **Enabled** switch decides whether customers can pay with gift cards, so leave it enabled. Checkout never offers the method to customers, so it does not need disabling to keep it out of checkout. A shop seeded with fixtures gets it from the `setono_gift_card_payment_method` fixture, which the plugin's fixture suite includes.
