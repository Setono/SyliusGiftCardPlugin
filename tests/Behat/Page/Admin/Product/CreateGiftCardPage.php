<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Page\Admin\Product;

use Sylius\Behat\Page\Admin\Product\CreateSimpleProductPage;

final class CreateGiftCardPage extends CreateSimpleProductPage implements CreateGiftCardPageInterface
{
    public function getRouteName(): string
    {
        return 'setono_sylius_gift_card_admin_product_create_gift_card';
    }
}
