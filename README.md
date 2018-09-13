## Installation

1. Require plugin with composer:

    ```bash
    composer require setono/gift-card-plugin
    ```

2. Import configuration:

    ```yaml
    imports:
        - { resource: "@SetonoSyliusGiftCardPlugin/Resources/config/config.yml" }
    ```
3. Import routing:
   
    ```yaml
    setono_sylius_gift_card_plugin:
        resource: "@SetonoSyliusGiftCardPlugin/Resources/config/routing.yml"
    ```

4. Add plugin class to your `AppKernel`:

    ```php
    $bundles = [
        new \Setono\SyliusGiftCardPlugin\SetonoSyliusGiftCardPlugin(),
    ];
    ```
5. Update your database:

    ```bash
    $ bin/console doctrine:migrations:diff
    $ bin/console doctrine:migrations:migrate
    ```

6. Copy templates from `vendor/setono/gift-card-plugin/src/Resources/views/SyliusShopBundle/` 
   to `app/Resources/SyliusShopBundle/views/` and  `vendor/setono/gift-card-plugin/src/Resources/views/SyliusAdminBundle/` to `app/Resources/SyliusAdminBundle/views/`.
   
7. Overwrite the grid `sylius_admin_product`:

    ```yml
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
                                        label: setono_sylius_gift_card_plugin.ui.gift_card
                                        icon: plus
                                        route: setono_sylius_gift_card_plugin_admin_product_create_gift_card
    ```

8. Install assets:

    ```bash
    bin/console assets:install --symlink web
    ```

9. Clear cache:

    ```bash
    bin/console cache:clear
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
