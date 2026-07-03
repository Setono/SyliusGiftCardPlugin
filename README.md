# Sylius Gift Card Plugin

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]

Add gift card functionality to your Sylius store:

- **Buy gift cards** — customers choose the amount, a design and an optional message, and pick whether the gift card is **virtual** (delivered by email as a PDF) or **physical** (shipped like a normal product).
- **Redeem gift cards** — customers apply a gift card code in the cart. You choose, via configuration, whether redeeming a gift card creates an **order adjustment** or a **real payment entity**.
- **Admin management** — a gift card grid, gift card designs, a one-click "create gift card product" scaffold, manual balance adjustments (with an audit ledger), and an outstanding-balance dashboard.

> This is the `1.x` line for **Sylius 1.13 / 1.14**. It is a ground-up rewrite of the `0.12.x` plugin. There is **no API layer** in 1.x — see [`UPGRADE-1.0.md`](UPGRADE-1.0.md) if you are coming from `0.12.x`.

## How it works

### Virtual vs physical

Whether a gift card is virtual or physical is derived from the chosen product variant's `shipping required` flag — there is no special product type. The recommended setup is a single gift card product with a "delivery" product option producing a non-shippable *Virtual* variant and a shippable *Physical* variant. Virtual-only stores work too: just create a single non-shippable variant and the delivery selector disappears. Use the **Create gift card product** button in the admin gift card list to scaffold this in one click.

### Buying a gift card

The customer chooses the amount, a design and an optional message on the product page (with a live preview). A disabled gift card is created per order item unit at add-to-cart time; at checkout completion it is reconciled against the final amounts, and when the order is paid it is enabled and emailed (with a PDF attachment) to the customer.

### Redeeming a gift card

The customer enters a gift card code in the cart. Depending on `setono_sylius_gift_card.redemption.mode`:

- **`adjustment`** (default): applied gift cards become negative order adjustments, reducing the order total. The gateway then charges the reduced total.
- **`payment`**: the order total stays intact; each applied gift card becomes a completed [`Payment`](https://docs.sylius.com/the-book/carts-and-orders/payments) using a lazily-created *offline* gift card payment method, and the remainder is charged through the normal gateway. The payment step is skipped automatically when gift cards cover the whole order.

In both modes gift cards cannot be used to buy other gift cards, balances are committed when the order is placed and restored when it is cancelled/refunded, and every balance change is recorded in an append-only ledger.

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

### Import routing

```yaml
# config/routes/setono_sylius_gift_card.yaml
setono_sylius_gift_card:
    resource: "@SetonoSyliusGiftCardPlugin/Resources/config/routes.yaml"
```

### Apply the traits/interfaces to your entities

Apply the plugin traits to your `Product`, `Order`, `OrderItem` and `OrderItemUnit` entities:

```php
// src/Entity/Product/Product.php
use Setono\SyliusGiftCardPlugin\Model\ProductInterface as SetonoSyliusGiftCardProductInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductTrait as SetonoSyliusGiftCardProductTrait;

class Product extends BaseProduct implements SetonoSyliusGiftCardProductInterface
{
    use SetonoSyliusGiftCardProductTrait;
}
```

```php
// src/Entity/Order/Order.php
use Setono\SyliusGiftCardPlugin\Model\OrderInterface as SetonoSyliusGiftCardOrderInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderTrait as SetonoSyliusGiftCardOrderTrait;

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
use Setono\SyliusGiftCardPlugin\Model\OrderItemTrait as SetonoSyliusGiftCardOrderItemTrait;

class OrderItem extends BaseOrderItem
{
    use SetonoSyliusGiftCardOrderItemTrait;
}
```

```php
// src/Entity/Order/OrderItemUnit.php
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface as SetonoSyliusGiftCardOrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitTrait as SetonoSyliusGiftCardOrderItemUnitTrait;

class OrderItemUnit extends BaseOrderItemUnit implements SetonoSyliusGiftCardOrderItemUnitInterface
{
    use SetonoSyliusGiftCardOrderItemUnitTrait;
}
```

Register the entity overrides in `config/packages/_sylius.yaml` (see `tests/Application` for a complete working example).

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
        mode: adjustment                 # 'adjustment' or 'payment'
        payment_method_code: gift_card   # code of the (auto-created) payment method used in payment mode
    pdf:
        page_size: A6                    # any page size supported by dompdf
```

### Customizing the PDF

Gift cards render to PDF with [dompdf](https://github.com/dompdf/dompdf). Override `@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig` to change the layout, or replace/decorate `Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface` to use a different engine.

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

## Development

```bash
composer install
(cd tests/Application && yarn install && yarn build)
composer phpunit        # unit + functional tests
composer analyse        # PHPStan (max level)
composer check-style    # ECS
```

See [`CLAUDE.md`](CLAUDE.md) for the full development workflow, including Playwright-based UI verification against the bundled `tests/Application`.

[ico-version]: https://img.shields.io/packagist/v/setono/sylius-gift-card-plugin.svg
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg
[ico-github-actions]: https://github.com/Setono/SyliusGiftCardPlugin/workflows/build/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-gift-card-plugin
[link-github-actions]: https://github.com/Setono/SyliusGiftCardPlugin/actions
