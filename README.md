# Sylius Gift Card Plugin

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]

Add gift card functionality to your Sylius store:

- **Buy gift cards** — customers choose the amount, a design and an optional message, and pick whether the gift card is **virtual** (delivered by email as a PDF) or **physical** (shipped like a normal product).
- **Redeem gift cards** — customers apply a gift card code in the cart, and it becomes a **real payment** against the order rather than a discount on it.
- **Admin management** — a gift card grid showing each card's status with filters to find a card by, issuing gift cards with the design and delivery type of your choice, gift card designs, a one-click "create gift card product" scaffold, manual balance adjustments (with an audit ledger naming the admin who made each one), and an outstanding-balance dashboard.

> This is the `1.x` line, for **Sylius 1.13 and up**. It is a ground-up rewrite of the `0.12.x` plugin. There is **no API layer** in 1.x — see [`UPGRADE-1.0.md`](UPGRADE-1.0.md) if you are coming from `0.12.x`.

## Table of contents

- [How it works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Customization](#customization)
- [Development](#development)
- [License](#license)

## How it works

### Virtual vs physical

Whether a gift card is virtual or physical is derived from the chosen product variant's `shipping required` flag — there is no special product type. The recommended setup is a single gift card product with a "delivery" product option producing a non-shippable *Virtual* variant and a shippable *Physical* variant. Virtual-only stores work too: just create a single non-shippable variant and the delivery selector disappears. Use the **Create gift card product** button in the admin gift card list to scaffold this in one click.

The delivery type also decides what the buyer is emailed when the order is paid. A virtual card *is* delivered by the email: the code is in the body and the card is attached as a PDF. A physical card is shipped with the code printed on it, so its email only says that the card will be shipped — emailing the code would make the card spendable before it arrives, and duplicate what is in the envelope. Set `delivery.email_physical_cards: true` if you want the code and the PDF emailed for physical cards anyway, as a digital backup. **Send email** on a gift card in the admin always includes the code and the PDF, whatever the delivery type: that is how you replace a physical card the customer lost or never received. It is only offered for a card the customer can use (enabled, not expired and with a balance left), so a card that is still waiting for its order to be paid, or that was disabled when its order was cancelled or refunded, is never sent.

A gift card issued in the admin is virtual unless you choose *Physical* on the create form, for a printed card you hand over or post yourself. No order or shipment comes with such a card, so its delivery type only records how it reaches the customer (the grid and the card's page show it), and the notification email sent when you issue it includes the code and the PDF whatever the type, like **Send email** does. The delivery type is settled when a card is issued and cannot be changed afterwards: a bought card takes it from its variant, which also decides whether its order ships it. The create form also takes the design printed on the card's PDF, picked from thumbnails of the designs enabled in the card's channel, or none for the default layout. The design can be changed on the edit form at any time; a card keeps its design when the design is disabled later.

### Buying a gift card

The customer chooses the amount, a design and an optional message on the gift card's product page, a page of the plugin's own built around a live preview of the card (see [The gift card product page](#the-gift-card-product-page)). The amount field starts out at the price of the variant the page preselects, in the channel, so the gift card product's price is the amount you suggest (the page does not print it as a price); a price the shop would refuse as an amount (zero, or outside `purchase.minimum_amount` / `maximum_amount`) leaves the field empty, and a shop whose maximum equals its minimum starts it at that one amount whatever the price. A disabled gift card is created per order item unit at add-to-cart time; at checkout completion it is reconciled against the final amounts, and when the order is paid it is enabled and emailed to the customer (virtual cards with their PDF attached, see [Virtual vs physical](#virtual-vs-physical)). Cancelling the order, or refunding it in full, disables the cards it bought; a partial refund does not, because it does not say which items the money went back for.

The **Create gift card product** button builds a gift card product whose inventory is not tracked, so it sells any number of cards. If you track it, for printed cards of which you have a limited stock, say, the stock is checked against all the cards of a variant in the cart together: every card added gets a cart line of its own, where another product's lines of the same variant merge into one, and Sylius' own stock checks look at one line at a time. The product page refuses cards that the cart's other lines of the variant leave no stock for, and the cart page and the checkout refuse a cart whose lines of the variant together hold more than is in stock.

Promotions never discount a gift card: a card is worth the amount the customer chose, so that is both what the customer pays for it and what the card holds. Unit discounts skip gift card lines, an order discount is taken from the other items only (a percentage of those items, spread over those items), and buying a gift card does not count towards a promotion's "item total" rule. Otherwise a coupon for the whole shop would sell full value gift cards at a discount. This also changes how promotions you already run behave: with "free shipping over 100.00" (an "item total" rule with a shipping discount), an order of a physical gift card for 100.00 used to ship for free and now pays for shipping, because buying a gift card is paying in advance rather than spending. To keep a promotion from applying at all to an order that buys a gift card, add the "Has no gift card" rule to it.

Sylius keeps an order's totals in integer columns, which hold 21,474,836.47 of a two decimal currency (2147483647 minor units, Sylius' `sylius_core.max_int_value`), and a cart past that ends in a server error when the database refuses it. The customer chooses what a gift card costs, so two large cards, or one at a quantity of two, would get there: the product page refuses a gift card that would take the cart's items total or order total past the limit, and the cart page refuses a gift card line's quantity raised past it. Both are validation errors that leave the cart as it was. The check runs before Sylius recalculates the cart, so it does not count what the recalculation adds besides the cards, such as a tax on the gift card product or shipping for a physical card: a cart that close to the limit can still overflow it. Ordinary products are left to Sylius, which lets them overflow a cart the same way at a high enough price and quantity, with the plugin or without it. An application that widens Sylius' order columns raises `sylius_core.max_int_value` with them, and the limit follows. Setting `purchase.maximum_amount` keeps ordinary carts far from it.

### Redeeming a gift card

The customer enters a gift card code in the cart. The order total stays intact and each applied gift card becomes a completed [`Payment`](https://docs.sylius.com/the-book/carts-and-orders/payments) made with the shop's *offline* gift card payment method (see [Create the gift card payment method](#create-the-gift-card-payment-method)); the remainder is charged through the normal gateway, and the payment step is skipped automatically when gift cards cover the whole order.

A gift card is treated as a means of payment rather than a discount, because that is what it is: selling one takes money for a liability the shop settles later, so redeeming it settles that liability instead of reducing what the order is worth. It also keeps gift cards out of the way of promotions, and matches what order management and accounting systems expect to receive.

Gift cards cannot be used to buy other gift cards, balances are committed when the order is placed and restored, once per payment, when the gift card payment is refunded (cancelling the order refunds it), and every balance change is recorded in an append-only ledger.

Below the order total, which the gift cards leave as it is, the customer sees what the gift cards cover and what remains to pay. The cart shows these figures, and so does every checkout step up to placing the order: below the summary in the sidebar of the address, shipping and payment steps, and below the order summary of the complete step. The complete step also lists the payment for the rest at the amount that is left (or no payment at all when the gift cards cover the whole order). The gift card payments are created when the order is placed, and from then on they are listed with the order's other payments, in the customer's account and in the admin. All of these figures come from one template, `@SetonoSyliusGiftCardPlugin/shop/cart/_gift_card_totals.html.twig`, rendered by the `setono_gift_card_totals` blocks (see [Moving or disabling the plugin's blocks](#moving-or-disabling-the-plugins-blocks)); in your own templates, the plugin's `setono_gift_card_covered_amount(order)` and `setono_gift_card_remaining_total(order)` Twig functions give the two figures.

An order the gift cards pay only in part stays *awaiting payment* until the rest is paid, although its gift card payments are completed when it is placed (Sylius alone would call it *partially paid*). Sylius' shop only lets a customer pay for an order, or change how to pay it, while the order awaits payment: from the thank you page, from the order in their account, and after a payment that did not go through at the payment provider. Sylius' unpaid order expiry (`sylius:cancel-unpaid-orders`) also only cancels orders that await payment, and cancelling one gives the gift cards their balance back. The plugin does this by decorating Sylius' order payment state resolver (`sylius.state_resolver.order_payment`); in the admin, such an order shows as awaiting payment with the completed gift card payment listed next to the payment for the rest.

The thank you page of such an order shows how to pay the rest, e.g. where to send a bank transfer. Sylius shows the instructions of the order's last payment there, and the gift card payment is added after the payment for the rest when the order is placed, so the plugin shows the instructions of the payment for the rest itself: the `setono_gift_card_payment_instructions` block on the `sylius.shop.order.thank_you.after_message` UI event, right above where Sylius' would be. An application that overrides `@SyliusShop/Order/thankYou.html.twig` keeps this as long as its template still fires that event; if the override shows the right instructions itself, disable the block through your own `sylius_ui` configuration. In your own templates, `setono_gift_card_remaining_payment(order)` gives the payment for the rest (`null` when the gift cards pay the whole order).

### Managing gift cards in the admin

The gift card grid and a card's page show one status per card:

- **Usable**: enabled, not expired and with a balance left. The only status a card can pay with
- **Expired**: enabled with a balance left, but past its expiry date
- **Spent**: enabled with nothing left on it, whether or not it has expired since
- **Pending**: created when the gift card was put in a cart, and waiting for its order to be paid
- **Disabled**: disabled by an admin, or because the order that bought it was cancelled or refunded in full. A card is only issued once its order is paid, so the card of an order cancelled before then (as `sylius:cancel-unpaid-orders` cancels every expired one) was never issued, but it waits for nothing any more and is disabled too

Where more than one would apply, the first of pending, disabled, spent and expired wins; `GiftCardInterface::getStatus()` gives it in your own code. The grid filters by code, part of the customer's email, channel, currency, delivery type, enabled, expired, spent and creation date, and sorts by code, customer, amount and creation date. Expired and spent look at the expiry date and the balance alone, so they also find a disabled card past its date or with nothing left. Pending cards are left out unless the *Pending gift cards* filter is set to show them, because most carts are never paid for and a pending card is no liability yet.

A card can only be deleted while nothing has happened to it: a card issued in the admin whose balance has not moved, or a card bought on an order that was never paid (pending, or disabled because that order was cancelled before it was paid). The grid only offers to delete those, the server refuses the others, and there is no bulk delete. A card's page links the order it was bought with, the orders it was applied to and its design, and adjusting the balance leads back to it, where the ledger lists the adjustment.

## Requirements

| Requirement | Version                                    |
|-------------|--------------------------------------------|
| PHP         | >= 8.1                                      |
| Sylius      | 1.13 and up (the `1.x` line)                |
| Symfony     | ^6.4 (symfony/form 6.4.31 and up)           |
| Twig        | below 3.29 ([why](#twig-below-329))         |
| ORM         | doctrine/orm (the only supported driver)   |

The plugin also builds on bundles every Sylius application already registers: LiipImagineBundle renders the design
thumbnails on the product page and in the admin, through the `setono_sylius_gift_card_design_thumbnail` and
`setono_sylius_gift_card_design_preview` filter sets the plugin adds, Sylius' image uploader stores the design images,
and SyliusFixturesBundle runs the plugin's fixtures.

## Installation

The steps below take a [Sylius-Standard](https://github.com/Sylius/Sylius-Standard) application to a working setup, so
the paths and class names are Sylius-Standard's (`src/Entity`, `config/packages/_sylius.yaml`); adjust them if your
application is laid out differently.

### Require the plugin with composer

```bash
composer require setono/sylius-gift-card-plugin
```

If Composer answers that the plugin conflicts with the `twig/twig` your application has locked, run the command again
with `--with-all-dependencies` (`-W`):

```bash
composer require setono/sylius-gift-card-plugin --with-all-dependencies
```

A fresh Sylius-Standard 1.14 locks Twig 3.29 or newer, so expect this there. `-W` allows Composer to move Twig back to
a 3.28 release, along with any package that depends on it.

#### Twig below 3.29

The plugin's `composer.json` conflicts with `twig/twig` `>=3.29`, on purpose:

- Twig 3.29 made the `Environment` a required argument of `TemplateWrapper::unwrap()`.
- `sylius/mailer-bundle` up to 2.2.0 calls `unwrap()` without one when it renders an email. On Twig 3.29 or newer, those
  releases fail every email they send with an `ArgumentCountError`. That includes the gift card emails, and Sylius'
  own order emails too.
- `sylius/mailer-bundle` 2.2.1 fixes the call, but it requires PHP 8.2. The plugin still supports PHP 8.1, where
  Composer can only install the older, broken releases.

Composer cannot limit the conflict to "Twig 3.29 or newer together with an older mailer bundle". So the plugin keeps
every installation on Twig below 3.29, which works with every mailer bundle release. The conflict will be lifted
when the plugin drops PHP 8.1. It will then require `sylius/mailer-bundle` 2.2.1 or newer instead.

### Register the plugin

Add it to `config/bundles.php` **before** `SyliusGridBundle`:

```php
$bundles = [
    // ...
    Setono\SyliusGiftCardPlugin\SetonoSyliusGiftCardPlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    // ...
];
```

The plugin auto-configures the state machine, grids, UI events, email templates and image filters for you — you do **not** need to import any bundle configuration manually. Its fixtures are the exception, see [Load the fixtures](#load-the-fixtures-optional).

What the plugin adds to Sylius' pages, the "Gift card" checkbox on the admin product form included, is rendered by blocks on Sylius' UI events, so you can move or disable each of them through your own `sylius_ui` configuration, see [Moving or disabling the plugin's blocks](#moving-or-disabling-the-plugins-blocks).

#### State machine adapters

The plugin works under both state machine adapters Sylius supports, Winzou State Machine and Symfony Workflow: it
registers winzou callbacks *and* the equivalent Symfony Workflow subscribers, so it behaves the same whichever adapter
applies a transition. Only that adapter emits the transition's events, so the work is never done twice. There is
nothing to configure on the plugin's side.

Sylius applies a graph's transitions through the adapter `sylius_state_machine_abstraction.graphs_to_adapters_mapping`
maps that graph to, or else through the one `sylius_state_machine_abstraction.default_adapter` names
(`winzou_state_machine` unless you change it). To run the four graphs the plugin hooks into on Symfony Workflow:

```yaml
# config/packages/sylius_state_machine_abstraction.yaml
sylius_state_machine_abstraction:
    graphs_to_adapters_mapping:
        sylius_order: symfony_workflow
        sylius_order_checkout: symfony_workflow
        sylius_order_payment: symfony_workflow
        sylius_payment: symfony_workflow
```

To run every graph on it instead:

```yaml
# config/packages/sylius_state_machine_abstraction.yaml
sylius_state_machine_abstraction:
    default_adapter: symfony_workflow
```

The same keys under `sylius_core.state_machine` change nothing: Sylius accepts that node, but neither 1.13 nor 1.14
reads it, and 1.14 marks it deprecated. Sylius' `UPGRADE-1.14.md` names `sylius_state_machine_abstraction.state_machine`
as its replacement, but there is no such node; the keys sit directly under `sylius_state_machine_abstraction`, as above.

### Import routing

```yaml
# config/routes/setono_sylius_gift_card.yaml
setono_sylius_gift_card:
    resource: "@SetonoSyliusGiftCardPlugin/Resources/config/routes.yaml"
```

This file puts the shop routes under `/{_locale}` and the admin routes under your admin path, as Sylius-Standard does
with Sylius' own: `/admin`, or whatever `SYLIUS_ADMIN_ROUTING_PATH_NAME` names. The plugin's admin pages sit behind the
admin firewall with the rest of the admin, wherever it lives.

If the URLs of your shop carry no locale, because you
[disabled Sylius' localised URLs](https://old-docs.sylius.com/en/1.14/cookbook/shop/disabling-localised-urls.html),
import `routes_no_locale.yaml` instead. It puts the shop routes at the root of the shop, as your `sylius_shop` import
does with Sylius' own, and the admin routes under your admin path like `routes.yaml` does:

```yaml
# config/routes/setono_sylius_gift_card.yaml
setono_sylius_gift_card:
    resource: "@SetonoSyliusGiftCardPlugin/Resources/config/routes_no_locale.yaml"
```

### Apply the traits/interfaces to your entities

Apply the plugin traits to your `Product`, `Order`, `OrderItem` and `OrderItemUnit` entities. Sylius-Standard already
has these classes in `src/Entity` and registers them in `config/packages/_sylius.yaml`, often with other plugins'
interfaces and traits on them (Sylius-Standard 1.14 puts the Mollie plugin's on `Order` and `Product`): add the gift
card interface and trait next to those rather than replacing the class. The traits carry their Doctrine mapping as
PHP 8 attributes *and* as annotations, so they work whether your application maps its entities with `type: attribute`
(the Sylius-Standard default in `config/packages/doctrine.yaml`) or `type: annotation`. The samples below are
attribute-mapped:

```php
<?php

// src/Entity/Product/Product.php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface as SetonoSyliusGiftCardProductInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductTrait as SetonoSyliusGiftCardProductTrait;
use Sylius\Component\Core\Model\Product as BaseProduct;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements SetonoSyliusGiftCardProductInterface
{
    use SetonoSyliusGiftCardProductTrait;
}
```

```php
<?php

// src/Entity/Order/Order.php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface as SetonoSyliusGiftCardOrderInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderTrait as SetonoSyliusGiftCardOrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder implements SetonoSyliusGiftCardOrderInterface
{
    use SetonoSyliusGiftCardOrderTrait {
        SetonoSyliusGiftCardOrderTrait::__construct as private __giftCardTraitConstruct;
    }

    public function __construct()
    {
        $this->__giftCardTraitConstruct();
        parent::__construct();
    }
}
```

`OrderTrait` also overrides `getPromotionSubjectTotal()` and `getNonDiscountedItemsTotal()`, so promotions leave the gift cards being bought out of what they discount. If your `Order` overrides either method as well, build on the trait's version (import it under an alias, like the constructor above). Otherwise an order discount is worked out on the gift cards too and put on the other items.

```php
<?php

// src/Entity/Order/OrderItem.php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\OrderItemTrait as SetonoSyliusGiftCardOrderItemTrait;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem
{
    use SetonoSyliusGiftCardOrderItemTrait;
}
```

```php
<?php

// src/Entity/Order/OrderItemUnit.php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface as SetonoSyliusGiftCardOrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitTrait as SetonoSyliusGiftCardOrderItemUnitTrait;
use Sylius\Component\Core\Model\OrderItemUnit as BaseOrderItemUnit;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item_unit')]
class OrderItemUnit extends BaseOrderItemUnit implements SetonoSyliusGiftCardOrderItemUnitInterface
{
    use SetonoSyliusGiftCardOrderItemUnitTrait;
}
```

### Apply the trait/interface to your customer repository

The customer field on the admin's gift card form finds customers by part of their email address, with a query the
plugin adds to Sylius' customer repository through `CustomerRepositoryTrait`. Without it, typing in the field fails
with `Entity 'App\Entity\Customer\Customer' has no field 'emailPartForGiftCard'` and no customer can be picked.
Sylius-Standard keeps Sylius' customer repository, so create one of your own:

```php
<?php

// src/Repository/CustomerRepository.php

declare(strict_types=1);

namespace App\Repository;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\CustomerRepositoryTrait as SetonoSyliusGiftCardCustomerRepositoryTrait;
use Setono\SyliusGiftCardPlugin\Repository\CustomerRepositoryInterface as SetonoSyliusGiftCardCustomerRepositoryInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\CustomerRepository as BaseCustomerRepository;

class CustomerRepository extends BaseCustomerRepository implements SetonoSyliusGiftCardCustomerRepositoryInterface
{
    use SetonoSyliusGiftCardCustomerRepositoryTrait;
}
```

If your application already has a customer repository, add the interface and the trait to it instead.

### Register the entities and the repository

Point Sylius at your classes in `config/packages/_sylius.yaml`. Sylius-Standard already has every line below except the
customer `repository`, so merge them into the keys that are there rather than adding a second `sylius_customer` (or
`sylius_order`, `sylius_product`) key:

```yaml
# config/packages/_sylius.yaml
parameters:
    # the public directory, where the gift card PDF finds the design images (in media/image)
    sylius_core.public_dir: '%kernel.project_dir%/public'

sylius_customer:
    resources:
        customer:
            classes:
                model: App\Entity\Customer\Customer
                repository: App\Repository\CustomerRepository

sylius_order:
    resources:
        order:
            classes:
                model: App\Entity\Order\Order
        order_item:
            classes:
                model: App\Entity\Order\OrderItem
        order_item_unit:
            classes:
                model: App\Entity\Order\OrderItemUnit

sylius_product:
    resources:
        product:
            classes:
                model: App\Entity\Product\Product
```

Mind `sylius_core.public_dir` if your application is not based on Sylius-Standard: Sylius' own default is
`%kernel.project_dir%/web`, and with it the PDF cannot find the design images and renders the card without them.

### Update the database

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

The plugin ships no migrations of its own. The diff creates its tables, all prefixed `setono_sylius_gift_card__` (the
table joining orders to the gift cards applied to them included), and adds the `gift_card` column to `sylius_product`.
Review it before you run it: it also picks up any other difference between your mapping and your database.

### Create the default gift card design

Customers pick a design on the gift card product page, and a channel without any enabled designs simply skips
the picker. To make the bundled "Classic" design available in every channel, run the command below. It is
idempotent — it creates the design when it is missing and adds it to the channels it is not in yet — so run it
again after you add a channel.

```bash
bin/console setono:gift-card:create-default-design
```

The command gives the design the plugin's bundled artwork. To create it with your own, point the
`setono_sylius_gift_card.default_design_image_path` parameter at another image before you run it; it only matters when
the design is created, so change the image of an existing design in the admin instead:

```yaml
# config/services.yaml
parameters:
    setono_sylius_gift_card.default_design_image_path: '%kernel.project_dir%/assets/gift-card.png'
```

A channel without an enabled design still works: the product page shows no design picker and the card renders its framed default. The admin does point it out, though: while a channel sells gift cards without an enabled design, every admin page carries a warning in the top bar and the gift card and design indexes explain how to fix it.

### Create the gift card payment method

Every redeemed gift card becomes a payment made with a payment method of its own, an *offline* one with the code
`gift_card` (the `redemption.payment_method_code` setting). The plugin does not create it on the fly, so create it once.

Until the method exists the shop refuses every gift card a customer tries to pay with ("Gift cards cannot be used in this
shop at the moment"), and every admin page carries a warning in the top bar. On the gift card, design and payment method
indexes the warning has a **Create gift card payment method** button: it creates the method, named in every language of
the shop, and the warning is gone.

To set it up from a deploy script instead, run the command below. Like the button, it is idempotent: when the method
exists, whoever created it, it is left as it is.

```bash
bin/console setono:gift-card:create-payment-method
```

You can also seed it with the `setono_gift_card_payment_method` fixture, which the plugin's fixture suite includes (see
[Load the fixtures](#load-the-fixtures-optional)), or create it yourself in the admin as an offline payment method with
that code.

The button, the command and the fixture create the method **in no channel**, and it needs none. The plugin finds the
method by its code alone, so gift card payments are made with it in every channel, whichever channels it is in, a
channel opened later included. What does look at a payment method's channels only offers it as a choice: Sylius'
checkout, which never offers this method to customers anyway, and [Sylius RefundPlugin](https://github.com/Sylius/RefundPlugin),
which lists the offline payment methods of an order's channel as refund destinations. In no channel, the gift card
payment method stays off that list. A method an administrator has put in channels is left as it is and works the same.

The method's create and edit pages in the admin say so above Sylius' form: the plugin finds the method by its code, so
the code has to stay `gift_card` (or whatever `redemption.payment_method_code` is), its channels make no difference to
gift card payments, which work in every channel, and it is in no channel by default because customers never choose it at
checkout and Sylius RefundPlugin would otherwise list it as a refund destination. No other payment method's pages show
the message.

### Create a gift card product

Customers buy gift cards through a product flagged as a gift card product: the **Gift card** checkbox on the admin's
product form. The **Create gift card product** button on the admin's gift card index creates the recommended one, a
product in every channel with a *Virtual* and a *Physical* variant (see [Virtual vs physical](#virtual-vs-physical)).
It is created disabled, so you can review it first; once you enable it, the shop shows it on the
[gift card product page](#the-gift-card-product-page).

### Load the fixtures (optional)

The plugin's fixtures are not loaded with the rest of its configuration. To add them to Sylius' `default` fixture
suite, import them:

```yaml
# config/packages/setono_sylius_gift_card.yaml
imports:
    - { resource: "@SetonoSyliusGiftCardPlugin/Resources/config/app/fixtures.yaml" }
```

`bin/console sylius:fixtures:load` then also seeds the "Classic" design, a gift card product, 20 gift cards with a
random balance and the gift card payment method, so a seeded shop needs none of the three steps above.

Mind the units when you write fixtures of your own: the `amount` of a `setono_gift_card` fixture is in major units
(`amount: 25` issues a card holding 25.00), while the `price` of a `setono_gift_card_product` fixture is in minor units
(`price: 5000` is 50.00), like the `purchase` settings of the [configuration](#configuration).

Both kinds of gift card can be seeded. A `setono_gift_card_product` fixture creates a variant for each delivery type
unless `delivery_types` names the ones it should have, and a `setono_gift_card` fixture issues a virtual card unless its
entry says `delivery_type: physical`. A product with a single delivery type gets no delivery option, so the shop shows
no variant choice for it, as for the single variant product described under [Virtual vs physical](#virtual-vs-physical).
Below the import, this narrows the imported suite's gift card product to its virtual variant and adds a physical card to
its gift cards:

```yaml
# config/packages/setono_sylius_gift_card.yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                setono_gift_card_product:
                    options:
                        custom:
                            gift_card:                   # the imported suite's product, narrowed to its virtual variant
                                delivery_types: [virtual]  # virtual and/or physical; both when left out
                setono_gift_card:
                    options:
                        custom:
                            plastic_card:                # a card of your own, next to the imported suite's 20
                                amount: 50
                                delivery_type: physical  # virtual or physical; virtual when left out
```

### Install assets

```bash
bin/console assets:install
```

The gift card product page loads the plugin's script and stylesheet straight from `public/bundles/setonosyliusgiftcardplugin`,
which this command fills; nothing goes through your Webpack Encore build. See
[Assets and Content Security Policy](#assets-and-content-security-policy) if your shop sends a Content Security Policy.

## Configuration

All settings are optional and shown here with their defaults:

```yaml
# config/packages/setono_sylius_gift_card.yaml
setono_sylius_gift_card:
    code_length: 16                      # significant characters in a generated code (shown grouped, e.g. ABCD-EFGH-…); at least minimum_code_length, at most 255
    minimum_code_length: 12              # fewest significant characters of any code a card is issued with, generated or typed; 12 to 255, never below 12 because a code is a bearer token and must not be guessable; see below
    default_validity_period: '3 years'   # how long a card stays valid (an interval such as '18 months' or '1 year 6 months'), or null to never expire; see below
    purchase:
        minimum_amount: 100              # minor units (e.g. cents), at least 1
        maximum_amount: ~                # minor units, at least minimum_amount, or ~ for no maximum
        maximum_message_length: 200      # characters a customer may write on the card, 1 to 65535
    delivery:
        email_physical_cards: false      # true also emails the code and the PDF of a *physical* card when the order is paid, as a backup
    redemption:
        payment_method_code: gift_card   # code of the payment method a redeemed gift card is paid with, see "Create the gift card payment method"
        rate_limiter: limiter.setono_sylius_gift_card_apply         # throttles attempts to apply a code per visitor (session), see below; ~ turns it off
        ip_rate_limiter: limiter.setono_sylius_gift_card_apply_ip   # throttles them per client IP, see below; ~ turns it off
    pdf:
        page_size: A6                    # any page size supported by dompdf; the card scales to fill it
```

A value outside its bounds stops the container from compiling, with a message naming the setting. The tree also has a
`resources` key, for replacing the plugin's models and repositories, see
[Overriding models, repositories and factories](#overriding-models-repositories-and-factories).

`code_length` and `minimum_code_length` can also be taken from an environment variable, such as
`code_length: '%env(int:GIFT_CARD_CODE_LENGTH)%'`, from symfony/config 6.4.37 (before it, Symfony refuses an
environment variable for an integer option with a minimum). Its value is only known at runtime, so the container
compiles whatever the variable holds, and the rules are applied where the value is used instead: the code generator
throws rather than generate a code while `code_length` is below 12, above 255 or below `minimum_code_length`, and the
*New gift card* form and the fixture throw rather than hold a code to `minimum_code_length` while it is outside 12 to
255, with a message naming the setting. Only issuing a gift card fails that way; the rest of the shop keeps working.

`default_validity_period` counts from when the order is placed for a gift card bought in the shop, and from its
creation for a gift card issued in the admin (where the expiry date can also be changed on the form). A bought card
expires at the end of the day the period after checkout completion, however long it sat in the cart before, so every
card bought on one order expires on the same day. It does not count from payment, even when that comes days later, as
with a bank transfer. A change to the setting applies to the cards bought or issued after it; existing cards keep their
expiry.

`default_validity_period` takes an interval that moves a date forward, in units `strtotime()` knows, such as
`'3 years'`, `'18 months'`, `'2 weeks'`, `'90 days'` or `'1 year 6 months'`, and nothing else. Whether `strtotime()`
can read a value is not the test, since it reads far more than intervals: it reads a unit it does not know (`'3 yrs'`,
`'18 mnths'`) or a bare number (`'3'`) as a timezone, which would make every card expire the day it is issued. Such a
value stops the container from compiling, and so do a day name (`'3 mon'` is the third Monday from now, not 3 months),
a date, a time of day, a timezone, and an interval of nothing or one that goes back (`'0 days'`, `'-1 year'`,
`'3 years ago'`).

`default_validity_period` can also be taken from an environment variable, such as
`default_validity_period: '%env(GIFT_CARD_VALIDITY)%'`. Its value is only known at runtime, so the container compiles
whatever the variable holds, and the interval is checked by the same rules where it is used instead. While the variable
holds anything but such an interval, whatever gives a gift card its expiry throws with a message naming the setting,
such as `The default_validity_period must be a valid strtotime interval, e.g. "3 years": "3 yrs"`: adding a gift card
to the cart, completing an order that buys one, the *New gift card* form, the fixture, and the admin's design preview,
which draws a sample card. The rest of the shop keeps working. The variable has to give a string, so `%env(int:...)%`
is refused while the container compiles. To let the variable mean *never expire*, read it as
`%env(default::GIFT_CARD_VALIDITY)%`: an empty or unset variable is then null. The container accepts `default::` here
from symfony/dependency-injection 6.4.19; before it, Symfony takes `default::` to give any type, an array included,
and refuses it for this option, as it did before the option could be taken from a variable at all.

`minimum_code_length` applies to every card issued from now on: `code_length` cannot be set below it, an admin who
types a code of their own on the *New gift card* form is held to it, and so is a code given to the `setono_gift_card`
fixture. A typed code is counted once it is normalized: it is saved in capitals with dashes and spaces dropped, the way
the cart looks codes up, so only its letters and digits count. Cards that already exist keep their code whatever its
length, so cards brought over from `0.12.x` with shorter codes stay usable and editable.

`purchase.maximum_amount` cannot be set below `purchase.minimum_amount`, since no amount would then be left for a
customer to buy: the container refuses it with `The maximum_amount (500) must be at least the minimum_amount (1000)`.
Setting both to the same amount sells gift cards of that one amount: the amount field starts out at it, whatever the
gift card product is priced at, and its help text names it. An amount taken from an environment variable is only known
at runtime, so the two are not compared when either is set that way.

`maximum_message_length` is what both forms allow: it sets the shop textarea's `maxlength` and remaining-characters
counter, and it is the limit enforced by the `GiftCardMessageLength` constraint on the gift card and on the shop's
gift card information, so raising the setting raises the limit everywhere. A line break counts as one character
everywhere too: browsers submit it as CR LF, and Symfony's textarea field turns it into a line feed before the message
is validated and stored (from symfony/form 6.4.31, which is why the plugin requires it). The card shows the message
with its line breaks intact and clamps it to four lines, so a message much longer than the default will be cut off on
the gift card and in its PDF.

A gift card holds at most 21,474,836.47 of its currency, a limit that matters in a currency of large nominal amounts
like the Indonesian rupiah. Balances are kept in minor units in columns mapped as Doctrine's `integer` type, a signed
32-bit integer, so the shop's amount field, the admin's *New gift card* and *Adjust balance* forms and the
`setono_gift_card` fixture refuse an amount or a balance beyond it rather than leave the database to refuse it. With no
`maximum_amount` this is the shop's maximum, and neither `minimum_amount` nor `maximum_amount` can be set above
2147483647 (`Configuration::MAXIMUM_AMOUNT`). Sylius keeps its prices and order totals in `integer` columns too.

### Protecting codes from guessing

Attempts to apply a code are throttled so codes cannot be brute forced. Each attempt counts against two buckets, and
both have to accept it: one per visitor (their session), 10 attempts per minute, and one per client IP, 50 attempts
per minute. The IP bucket stops a guesser that throws its session cookie away after every attempt. Everyone behind
one address shares it, an office NAT or a mobile carrier's CGNAT for example, which is why it allows five times as
many attempts as a single visitor gets (the ratio Symfony's login throttling uses as well).

The plugin registers both limiters under `framework.rate_limiter.limiters`, as `setono_sylius_gift_card_apply` and
`setono_sylius_gift_card_apply_ip` (sliding windows); change their values in your own `framework` configuration, or
point `rate_limiter` and `ip_rate_limiter` at any [rate limiter](https://symfony.com/doc/current/rate_limiter.html)
you configured yourself.

**Behind a reverse proxy or load balancer, configure
[`framework.trusted_proxies`](https://symfony.com/doc/current/deployment/proxies.html).** Without it, Symfony takes
the proxy's address for the client IP of every request, so all the shop's customers share one IP bucket: 50 attempts
a minute between them, after which everybody who tries a gift card code is told to wait, customers with a real card
included. If you cannot configure trusted proxies, set `ip_rate_limiter: ~` and rely on the session bucket alone.

Every rejected code gives the customer the same message, whatever the reason (unknown, disabled, expired,
empty, wrong channel or currency), so the form cannot be used to find out which codes exist. The actual
reason is written to the log at info level, with the code masked down to its last four characters
(`************MNOP`): a code is a bearer token, and logs travel.

## Customization

Every extension point below is a plain service or template you replace — no configuration flags required.

The plugin's services use each other through their interfaces, so to change one, decorate (or replace) the service
registered under its interface, e.g. `Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface`. What
you register there is what the whole plugin uses, the state machine callbacks included. The resources are the
exception: they follow Sylius' conventions, see [Overriding models, repositories and factories](#overriding-models-repositories-and-factories).

### The gift card product page

The shop shows a gift card product on a page of the plugin's own, `@SetonoSyliusGiftCardPlugin/shop/product/show.html.twig`,
instead of Sylius' product page. It is laid out like Sylius' page, so your theme styles it the same way: the live
preview of the card takes the place of the product images, and next to it are the product's name and short
description and Sylius' add to cart form, with what the customer chooses in the order they put the card together:
the delivery type (only when the product offers both: a product with a single delivery type has nothing to choose),
the amount, the design, the message and the quantity. A description you wrote for the product follows below. What
Sylius' page shows and a gift card has no use for is left out: the image placeholder, ratings and reviews, the price
(the amount field starts at it), the code, the variant table with a price per variant, the empty details tab and the
latest products.

The quantity stays, because buying several identical cards at once is a legitimate purchase (one for each member of a
team, say), and every unit becomes a card of its own with a code of its own, which the field's help says.

The add to cart form is Sylius' own: Sylius' partial route builds it (the plugin's form type extension adds the gift
card fields) and renders it with `@SetonoSyliusGiftCardPlugin/shop/product/show/_add_to_cart_form.html.twig`, and it
posts to Sylius' cart route with its CSRF token, so the cart handler and the redirect to the cart work as on any
product page.

The page is rendered by `Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardProductPageSubscriber`. Sylius' resource
controller dispatches `sylius.product.show` once it has found the product, and answers the request with the response
a listener sets on the event. The subscriber does that for a gift card product on the shop's product page
(`sylius_shop_product_show`), rendering the template with the variables Sylius' controller passes to its own
(`configuration`, `metadata`, `resource` and `product`), so the product is not looked up twice. The admin's product
page and the shop's partial product route fire the same event and are left alone, and so is a request for another
format than HTML. The subscriber runs at priority -100, after your own listeners on the event: one that answers the
request itself (a redirect, say) keeps its response.

To change the page:

- **Add, move or disable a part** in your `sylius_ui` configuration. Every part of the page is a block on one of the
  plugin's template events below, ten priorities apart so a block of yours fits in between (the blocks are listed
  under [Moving or disabling the plugin's blocks](#moving-or-disabling-the-plugins-blocks)):

  | Event | Where |
  |-------|-------|
  | `setono_sylius_gift_card.shop.product.show.header` | Above the two columns |
  | `setono_sylius_gift_card.shop.product.show.preview` | The first column |
  | `setono_sylius_gift_card.shop.product.show.purchase` | The second column |
  | `setono_sylius_gift_card.shop.product.show.add_to_cart_form` | Inside the add to cart form, above its button |
  | `setono_sylius_gift_card.shop.product.show.content` | Below the two columns |

  ```yaml
  # config/packages/sylius_ui.yaml
  sylius_ui:
      events:
          setono_sylius_gift_card.shop.product.show.add_to_cart_form:
              blocks:
                  # a note under the message field
                  gift_wrapping_note:
                      template: 'gift_card/_gift_wrapping_note.html.twig'
                      priority: 15
          setono_sylius_gift_card.shop.product.show.header:
              blocks:
                  breadcrumb:
                      enabled: false
  ```

  The blocks get the page's variables (`product` among them), and those inside the form `product`, `order_item` and
  `form`, the add to cart form.
- **Override a template** the way you override any bundle's, in `templates/bundles/SetonoSyliusGiftCardPlugin/`
  (e.g. `templates/bundles/SetonoSyliusGiftCardPlugin/shop/product/show.html.twig` for the whole page, or one of the
  block templates under `shop/product/show/`), or in a Sylius theme, under the theme's
  `templates/bundles/SetonoSyliusGiftCardPlugin/`. Where Sylius has a template for a part (the breadcrumb, the
  product's name), the block uses Sylius', so your override of it shows on this page too.
- **Keep Sylius' product page**, or a product page of your own, for gift card products after all, by overriding the
  page with a template that extends it, e.g. `{% extends '@SyliusShop/Product/show.html.twig' %}`. It gets the
  variables Sylius' page gets, and the `setono_gift_card_information` block puts the gift card fields into Sylius' add
  to cart form.

Sylius' own `sylius.shop.product.show.*` events do not fire on this page. A block you put on them for every product
page, a trust badge say, has to go on the plugin's events too to show on the gift card's page. The live preview script
works within the element with the id `setono-gift-card-information`, which the page puts around both columns: an
override has to keep the preview and the form inside it.

### Moving or disabling the plugin's blocks

Everything the plugin adds to Sylius' pages is a block on one of Sylius' UI events, so it stays in place when you
override a Sylius template that still fires the event, and you can move or disable each block in your own `sylius_ui`
configuration. The same goes for the blocks of the [gift card product page](#the-gift-card-product-page), which sit on
the plugin's own events:

| Event | Block | Priority | What it renders |
|-------|-------|----------|-----------------|
| `setono_sylius_gift_card.shop.product.show.header` | `breadcrumb` | 10 | Sylius' breadcrumb (`@SyliusShop/Product/Show/_breadcrumb.html.twig`) |
| `setono_sylius_gift_card.shop.product.show.preview` | `preview` | 10 | The live preview of the card |
| `setono_sylius_gift_card.shop.product.show.purchase` | `name` | 30 | The product's name, with Sylius' template (`@SyliusShop/Product/Show/_header.html.twig`) |
| `setono_sylius_gift_card.shop.product.show.purchase` | `short_description` | 20 | The product's short description, when it has one |
| `setono_sylius_gift_card.shop.product.show.purchase` | `add_to_cart` | 10 | Sylius' add to cart form, or Sylius' out of stock message |
| `setono_sylius_gift_card.shop.product.show.add_to_cart_form` | `delivery` | 50 | The choice of delivery type, a radio group, when the product offers more than one variant |
| `setono_sylius_gift_card.shop.product.show.add_to_cart_form` | `amount` | 40 | The amount field |
| `setono_sylius_gift_card.shop.product.show.add_to_cart_form` | `design` | 30 | The design picker, when the channel has enabled designs |
| `setono_sylius_gift_card.shop.product.show.add_to_cart_form` | `message` | 20 | The message field, with the count of characters left |
| `setono_sylius_gift_card.shop.product.show.add_to_cart_form` | `quantity` | 10 | The quantity field |
| `setono_sylius_gift_card.shop.product.show.content` | `description` | 10 | The product's description, when it has one |
| `sylius.shop.product.show.add_to_cart_form` | `setono_gift_card_information` | 10 | The amount, design and message fields with the live preview, wherever Sylius' own add to cart form is rendered for a gift card product (the shop's product page is the plugin's, which does not fire this event) |
| `sylius.shop.cart.summary` | `setono_gift_card_totals` | 18 | What the applied gift cards cover and what remains to pay, right below Sylius' totals (20) |
| `sylius.shop.cart.summary` | `setono_gift_cards` | 12 | The form to apply a code and the applied gift cards, above Sylius' checkout button (10) |
| `sylius.shop.checkout.sidebar` | `setono_gift_card_totals` | 18 | The same figures on the address, shipping and payment steps, right below Sylius' summary (20) and above its support box (10) |
| `sylius.shop.checkout.complete.summary` | `setono_gift_card_totals` | 8 | The same figures on the complete step, right below Sylius' order summary (10) |
| `sylius.shop.order.thank_you.after_message` | `setono_gift_card_payment_instructions` | -10 | The instructions for paying the rest, see [Redeeming a gift card](#redeeming-a-gift-card) |
| `sylius.admin.product.tab_details` | `setono_gift_card` | 10 | The **Gift card** checkbox on the product form |
| `sylius.admin.layout.topbar_middle` | `setono_gift_card_setup_warning` | 10 | The setup warning in the top bar of every admin page |
| `setono_sylius_gift_card.admin.gift_card.index` and `setono_sylius_gift_card.admin.gift_card_design.index` | `setono_gift_card_setup_warning` | 30 | The full setup warning above the gift card and design indexes |
| `sylius.admin.payment_method.index` | `setono_gift_card_payment_method_warning` | 30 | The warning about the missing gift card payment method, with its button, above the payment method index |
| `sylius.admin.payment_method.create` and `sylius.admin.payment_method.update` | `setono_gift_card_payment_method_message` | 12 | What the gift card payment method is, above the form on its own create and edit pages, between Sylius' header (20) and the form (10) |

```yaml
# config/packages/sylius_ui.yaml
sylius_ui:
    events:
        sylius.shop.cart.summary:
            blocks:
                # the form to apply a code above the totals instead of below them
                setono_gift_cards:
                    priority: 25
        sylius.shop.order.thank_you.after_message:
            blocks:
                setono_gift_card_payment_instructions:
                    enabled: false
```

Disabling a block only stops it from rendering, so render what it rendered yourself where it is still needed. That
matters most for the form blocks, because Sylius ends both forms with `render_rest: false`, so a field no block renders
is never submitted. On the gift card product page, a gift card cannot be added to the cart without the `amount` block,
as the amount the customer has to choose is missing, and a product offering both delivery types needs the `delivery`
block; the same goes for `setono_gift_card_information` wherever Sylius' own add to cart form is rendered for a gift
card product. Without the `quantity` block a customer buys one card at a time, and changes the quantity in the cart.
Without `setono_gift_card` the product form submits the checkbox as unchecked, so every save of a gift card product
turns it back into a normal one.

### Assets and Content Security Policy

The gift card product page brings its own assets rather than going through your Webpack Encore build. It includes
`bundles/setonosyliusgiftcardplugin/css/product-gift-card.css` with a `<link>` tag in the layout's `stylesheets` block
and `bundles/setonosyliusgiftcardplugin/js/product-gift-card.js` (plain JavaScript, no jQuery) with a
`<script defer src>` tag in its `javascripts` block, and the card's own styles as an inline `<style>` element
(`@SetonoSyliusGiftCardPlugin/shop/gift_card/_card_style.html.twig`, which the PDF shares) in the `preview` block. The
`setono_gift_card_information` block on Sylius' add to cart form includes the same three inside the form. A Content
Security Policy on the product page therefore has to allow scripts and styles from your own origin (`'self'`) and
inline styles (`style-src 'unsafe-inline'`). To add a nonce, or to serve the files through your own build, override
`@SetonoSyliusGiftCardPlugin/shop/product/show.html.twig` and
`@SetonoSyliusGiftCardPlugin/shop/product/show/_preview.html.twig` (and
`@SetonoSyliusGiftCardPlugin/shop/product/show/_gift_card_information.html.twig` where Sylius' form is used).

### Customizing the PDF

Gift cards render to PDF with [dompdf](https://github.com/dompdf/dompdf). Override `@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig` to change the layout, or replace/decorate `Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface` to use a different engine.

The PDF takes the design's images from the files Sylius' image uploader stored, in `media/image` below
`%sylius_core.public_dir%`, see [Register the entities and the repository](#register-the-entities-and-the-repository).

The card is laid out on a fixed 560×396 pixel grid — A6 landscape — and is scaled onto whatever `pdf.page_size` is configured, so the layout is defined in one place and works on any paper.

The texts on the card come from the plugin's translations (`setono_sylius_gift_card.pdf.*`, in English, Danish and French). The small print on the back, `setono_sylius_gift_card.pdf.terms`, is legal copy: the plugin's version ("not redeemable for cash and cannot be replaced if lost or stolen") is a placeholder, not your terms, so replace it in every language your shop uses. Translations in your application win over the plugin's:

```yaml
# translations/messages.en.yml
setono_sylius_gift_card:
    pdf:
        terms: 'Valid for three years from the date of purchase. See example.com/gift-card-terms.'
```

### Customizing the emails

The plugin sends two emails: `setono_sylius_gift_card__gift_card` (a single gift card, sent when one is created in the admin panel) and `setono_sylius_gift_card__gift_cards_from_order` (all gift cards from a paid order, sent to the buyer). Override their templates at `@SetonoSyliusGiftCardPlugin/email/gift_card.html.twig` and `@SetonoSyliusGiftCardPlugin/email/gift_cards_from_order.html.twig`, or redefine the emails under the `sylius_mailer` key to change the sender. Their subjects are the translations `setono_sylius_gift_card.email.new_gift_card` and `setono_sylius_gift_card.email.gift_cards_from_order_subject` (which gets the order number as `%number%`): Sylius takes the subject from the template's `subject` block, so override the translation, or the block, to change it. Both include `@SetonoSyliusGiftCardPlugin/email/_gift_cards.html.twig`, which renders the cards themselves — override that one to change how a card is presented in both emails at once.

### Changing what gift cards may pay for

By default gift cards may pay for everything except gift-card line items. To change this, decorate `Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface`:

```yaml
# config/services.yaml
services:
    App\GiftCard\EligibleTotalCalculator:
        decorates: Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface
        arguments: ['@.inner']
```

### Customizing the add-to-cart command

To capture the amount, message and design the customer picks, the plugin decorates Sylius' `sylius.factory.add_to_cart_command` so it produces a `Setono\SyliusGiftCardPlugin\Order\AddToCartCommand` — which implements `Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface` and carries the gift card information — and the add-to-cart form binds to that class.

If your application needs its own add-to-cart command, make it extend `AddToCartCommand` (or implement `AddToCartCommandInterface`) and point the command class parameter at it:

```yaml
# config/services.yaml
parameters:
    setono_sylius_gift_card.order.model.add_to_cart_command.class: App\Order\AddToCartCommand
```

The plugin verifies this at container compile time and fails with an actionable message if the configured class does not implement the interface. Its factory decorator is idempotent and applied outermost, so it also composes cleanly with a decorator of your own on `sylius.factory.add_to_cart_command`.

Sylius validates its own command with a stock check (`CartItemAvailability`: what is added, together with what the cart already holds of the variant, has to be in stock), mapped on its command class. The plugin maps the same check on `AddToCartCommandInterface`, so the add-to-cart form keeps it whichever command it is bound to, your own included. The stock check reads the line's variant without checking it, so the plugin runs it in sequence after `CartItemVariantRequired`, which refuses a line without a variant (a request that leaves out the variant choice of a product with several variants) on the variant field.

### Overriding models, repositories and factories

The plugin's resources (`gift_card`, `gift_card_design` with its translation, `gift_card_design_image` and `gift_card_transaction`) follow the standard Sylius resource configuration, so you can swap any model, repository, controller or factory for your own class:

```yaml
setono_sylius_gift_card:
    resources:
        gift_card:
            classes:
                model: App\Entity\GiftCard\GiftCard
```

Make a class of your own extend the plugin's, or implement the same interface, since that is what the plugin's services
expect (e.g. `Setono\SyliusGiftCardPlugin\Model\GiftCardInterface` for the gift card model, or
`Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface` for its repository). These are all the classes the
tree takes, with their defaults:

```yaml
setono_sylius_gift_card:
    resources:
        gift_card:
            classes:
                model: Setono\SyliusGiftCardPlugin\Model\GiftCard
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                repository: Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepository
                form: Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType
                factory: Sylius\Component\Resource\Factory\Factory
        gift_card_design:
            classes:
                model: Setono\SyliusGiftCardPlugin\Model\GiftCardDesign
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                repository: Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardDesignRepository
                form: Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignType
                factory: Sylius\Component\Resource\Factory\TranslatableFactory
            translation:
                classes:
                    model: Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation
                    repository: Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository
                    factory: Sylius\Component\Resource\Factory\Factory
        gift_card_design_image:
            classes:
                model: Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                repository: Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository
                factory: Sylius\Component\Resource\Factory\Factory
        gift_card_transaction:
            classes:
                model: Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                repository: Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository
                factory: Sylius\Component\Resource\Factory\Factory
```

## Development

```bash
composer install
(cd tests/Application && yarn install && yarn build)
composer phpunit        # unit + functional tests
composer analyse        # PHPStan (max level)
composer check-style    # ECS
```

See [`CLAUDE.md`](CLAUDE.md) for the full development workflow, including Playwright-based UI verification against the bundled `tests/Application`.

## License

This plugin is released under the [MIT License](LICENSE).

[ico-version]: https://img.shields.io/packagist/v/setono/sylius-gift-card-plugin.svg
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg
[ico-github-actions]: https://github.com/Setono/SyliusGiftCardPlugin/workflows/build/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-gift-card-plugin
[link-github-actions]: https://github.com/Setono/SyliusGiftCardPlugin/actions
