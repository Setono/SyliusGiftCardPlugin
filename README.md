# Sylius Gift Card Plugin

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travis]][link-travis]
[![Quality Score][ico-code-quality]][link-code-quality]

## Installation

### Require plugin with composer:

```bash
$ composer require setono/sylius-gift-card-plugin
```

### Import configuration:

```yaml
# config/packages/_sylius.yaml
imports:
    # ...
    - { resource: "@SetonoSyliusGiftCardPlugin/Resources/config/app/config.yaml" }
```

### (Optional) Import fixtures 

If you wish to have some gift cards to play with in your application 
during development.

```yaml
# config/packages/_sylius.yaml
imports:
    # ...
    - { resource: "@SetonoSyliusGiftCardPlugin/Resources/config/app/fixtures.yaml" }
```

Or write your own ones.

### Import routing:
   
```yaml
# config/routes.yaml
setono_sylius_gift_card:
    resource: "@SetonoSyliusGiftCardPlugin/Resources/config/routes.yaml"
```

### Add plugin class to your `bundles.php`:

```php
<?php
$bundles = [
    // ...
    Setono\SyliusGiftCardPlugin\SetonoSyliusGiftCardPlugin::class => ['all' => true],
];
```

### Update your database:

```bash
$ bin/console doctrine:migrations:diff
$ bin/console doctrine:migrations:migrate
```

### Copy templates

- For `SyliusShopBundle`:

    ```bash
    mkdir -p templates/bundles/SyliusShopBundle
    cp -r vendor/setono/sylius-gift-card-plugin/tests/Application/templates/bundles/SyliusShopBundle/ \
       templates/bundles/SyliusShopBundle/
    ```

- For `SyliusAdminBundle`:

    ```bash
    mkdir -p templates/bundles/SyliusAdminBundle
    cp -r vendor/setono/sylius-gift-card-plugin/tests/Application/templates/bundles/SyliusAdminBundle/ \
       templates/bundles/SyliusAdminBundle/
    ```   

### Override the grid `sylius_admin_product`:

- If you haven't override it in your app before - you can just import
  ready-to-go grid configuration:

    ```yaml
    # config/packages/_sylius.yaml
    imports:
        - { resource: "@SetonoSyliusGiftCardPlugin/Resources/config/grids/sylius_admin_product.yaml" }
    ```

- But if you override it before - make sure you have next changes in place:

```yaml
sylius_grid:
    grids:
        sylius_admin_product:
            actions:
                main:
                    create:
                        type: links
                        label: sylius.ui.create
                        options:
                            class: primary
                            icon: plus
                            header:
                                icon: cube
                                label: sylius.ui.type
                            links:
                                simple:
                                    label: sylius.ui.simple_product
                                    icon: plus
                                    route: sylius_admin_product_create_simple
                                configurable:
                                    label: sylius.ui.configurable_product
                                    icon: plus
                                    route: sylius_admin_product_create

                                # This is what should be added:
                                gift_card:
                                    label: setono_sylius_gift_card.ui.gift_card
                                    icon: plus
                                    route: setono_sylius_gift_card_admin_product_create_gift_card
```

### Install assets:

```bash
$ php bin/console assets:install --symlink web
```

### Clear cache:

```bash
$ php bin/console cache:clear
```
    
# Development

## Testing

```bash
$ composer tests
```

## Playing

To run built-in application showing plugin at work, just run:  

```bash
$ composer try
```

## Contribution

Learn more about our contribution workflow on http://docs.sylius.org/en/latest/contributing/.

Please, run `composer all` to run all checks and tests before making pull request.

[ico-version]: https://img.shields.io/packagist/v/setono/sylius-gift-card-plugin.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/Setono/SyliusGiftCardPlugin/master.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Setono/SyliusGiftCardPlugin.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/setono/sylius-gift-card-plugin
[link-travis]: https://travis-ci.org/Setono/SyliusGiftCardPlugin
[link-code-quality]: https://scrutinizer-ci.com/g/Setono/SyliusGiftCardPlugin
