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
imports:
    - { resource: "@SetonoSyliusGiftCardPlugin/Resources/config/app/config.yaml" }
```

### Import routing:
   
```yaml
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
Copy templates from


`vendor/setono/sylius-gift-card-plugin/tests/Application/templates/bundles/SyliusShopBundle/` to `templates/bundles/SyliusShopBundle/`
   
and from `vendor/setono/sylius-gift-card-plugin/tests/Application/templates/bundles/SyliusAdminBundle/` to `templates/bundles/SyliusAdminBundle/`.
   
### Overwrite the grid `sylius_admin_product`:

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
    
## Testing

```bash
$ composer install
$ cd tests/Application
$ yarn install
$ yarn run gulp
$ bin/console assets:install web -e test
$ bin/console doctrine:database:create -e test
$ bin/console doctrine:schema:create -e test
$ bin/console server:run 127.0.0.1:8080 -d web -e test
$ bin/behat
$ bin/phpspec run
```

## Contribution

Learn more about our contribution workflow on http://docs.sylius.org/en/latest/contributing/.

[ico-version]: https://img.shields.io/packagist/v/setono/sylius-gift-card-plugin.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/Setono/SyliusGiftCardPlugin/master.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Setono/SyliusGiftCardPlugin.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/setono/sylius-gift-card-plugin
[link-travis]: https://travis-ci.org/Setono/SyliusGiftCardPlugin
[link-code-quality]: https://scrutinizer-ci.com/g/Setono/SyliusGiftCardPlugin
