# Sylius Gift Card Plugin

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]

Add gift card functionality to your Sylius store:

- **Buy gift cards** — customers choose the amount, a design and an optional message, and pick whether the gift card is **virtual** (delivered by email as a PDF) or **physical** (shipped like a normal product).
- **Redeem gift cards** — customers apply a gift card code in the cart, and it becomes a **real payment** against the order rather than a discount on it.
- **Admin management** — a gift card grid, gift card designs, a one-click "create gift card product" scaffold, manual balance adjustments (with an audit ledger), and an outstanding-balance dashboard.

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

### Buying a gift card

The customer chooses the amount, a design and an optional message on the product page (with a live preview). A disabled gift card is created per order item unit at add-to-cart time; at checkout completion it is reconciled against the final amounts, and when the order is paid it is enabled and emailed (with a PDF attachment) to the customer.

### Redeeming a gift card

The customer enters a gift card code in the cart. The order total stays intact and each applied gift card becomes a completed [`Payment`](https://docs.sylius.com/the-book/carts-and-orders/payments) using a lazily-created *offline* gift card payment method; the remainder is charged through the normal gateway, and the payment step is skipped automatically when gift cards cover the whole order.

A gift card is treated as a means of payment rather than a discount, because that is what it is: selling one takes money for a liability the shop settles later, so redeeming it settles that liability instead of reducing what the order is worth. It also keeps gift cards out of the way of promotions, and matches what order management and accounting systems expect to receive.

Gift cards cannot be used to buy other gift cards, balances are committed when the order is placed and restored when it is cancelled/refunded, and every balance change is recorded in an append-only ledger.

## Requirements

| Requirement | Version                                    |
|-------------|--------------------------------------------|
| PHP         | >= 8.1                                      |
| Sylius      | 1.13 and up (the `1.x` line)                |
| Symfony     | ^6.4                                        |
| ORM         | doctrine/orm (the only supported driver)   |

## Installation

### Require the plugin with composer

```bash
composer require setono/sylius-gift-card-plugin
```

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

The plugin auto-configures the state machine, grids, UI events, email templates and image filters for you — you do **not** need to import any bundle configuration manually.

Both state machine adapters Sylius supports are covered: the plugin registers winzou callbacks *and* the
equivalent Symfony Workflow subscribers, so it behaves the same whichever adapter
`sylius_core.state_machine.default_adapter` is set to. Only the adapter actually applying a transition emits
its events, so the work is never done twice.

### Import routing

```yaml
# config/routes/setono_sylius_gift_card.yaml
setono_sylius_gift_card:
    resource: "@SetonoSyliusGiftCardPlugin/Resources/config/routes.yaml"
```

### Apply the traits/interfaces to your entities

Apply the plugin traits to your `Product`, `Order`, `OrderItem` and `OrderItemUnit` entities. The traits carry their
Doctrine mapping as PHP 8 attributes *and* as annotations, so they work whether your application maps its entities
with `type: attribute` (the Sylius-Standard default in `config/packages/doctrine.yaml`) or `type: annotation`. The
samples below are attribute-mapped:

```php
// src/Entity/Product/Product.php
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
// src/Entity/Order/Order.php
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

```php
// src/Entity/Order/OrderItem.php
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
// src/Entity/Order/OrderItemUnit.php
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

Register the entity overrides in `config/packages/_sylius.yaml` (see `tests/Application` for a complete, attribute-mapped working example).

### Update the database

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### Install assets

```bash
bin/console assets:install
```

## Configuration

All settings are optional and shown here with their defaults:

```yaml
# config/packages/setono_sylius_gift_card.yaml
setono_sylius_gift_card:
    code_length: 16                      # significant characters in a generated code (shown grouped, e.g. ABCD-EFGH-…)
    default_validity_period: '3 years'   # any strtotime-compatible interval, or null to never expire
    purchase:
        minimum_amount: 100              # minor units (e.g. cents)
        maximum_amount: ~                # null = no maximum
    redemption:
        payment_method_code: gift_card   # code of the (auto-created) payment method a redeemed gift card is paid with
    pdf:
        page_size: A6                    # any page size supported by dompdf
```

## Customization

Every extension point below is a plain service or template you replace — no configuration flags required.

### Customizing the PDF

Gift cards render to PDF with [dompdf](https://github.com/dompdf/dompdf). Override `@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig` to change the layout, or replace/decorate `Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface` to use a different engine.

### Customizing the emails

The plugin sends two emails: `setono_sylius_gift_card__gift_card` (a single gift card, sent when one is created in the admin panel) and `setono_sylius_gift_card__gift_cards_from_order` (all gift cards from a paid order, sent to the buyer). Override their templates at `@SetonoSyliusGiftCardPlugin/email/gift_card.html.twig` and `@SetonoSyliusGiftCardPlugin/email/gift_cards_from_order.html.twig`, or redefine the emails under the `sylius_mailer` key to change the sender or subject.

### Changing what gift cards may pay for

By default gift cards may pay for everything except gift-card line items. Decorate `Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface` to change this.

### Customizing the add-to-cart command

To capture the amount, message and design the customer picks, the plugin decorates Sylius' `sylius.factory.add_to_cart_command` so it produces a `Setono\SyliusGiftCardPlugin\Order\AddToCartCommand` — which implements `Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface` and carries the gift card information — and the add-to-cart form binds to that class.

If your application needs its own add-to-cart command, make it extend `AddToCartCommand` (or implement `AddToCartCommandInterface`) and point the command class parameter at it:

```yaml
# config/services.yaml
parameters:
    setono_sylius_gift_card.order.model.add_to_cart_command.class: App\Order\AddToCartCommand
```

The plugin verifies this at container compile time and fails with an actionable message if the configured class does not implement the interface. Its factory decorator is idempotent and applied outermost, so it also composes cleanly with a decorator of your own on `sylius.factory.add_to_cart_command`.

### Overriding models, repositories and factories

The `gift_card`, `gift_card_design` and `gift_card_transaction` resources follow the standard Sylius resource configuration, so you can swap any model, repository, controller or factory for your own class:

```yaml
setono_sylius_gift_card:
    resources:
        gift_card:
            classes:
                model: App\Entity\GiftCard\GiftCard
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
