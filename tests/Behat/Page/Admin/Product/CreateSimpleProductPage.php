<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Behat\Page\Admin\Product;

use Sylius\Behat\Page\Admin\Product\CreateSimpleProductPage as BaseCreateSimpleProductPage;

final class CreateSimpleProductPage extends BaseCreateSimpleProductPage implements CreateSimpleProductPageInterface
{
    public function specifyGiftCard(bool $val): void
    {
        $this->getElement('gift_card')->setValue($val);
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'gift_card' => '#sylius_admin_product_giftCard',
        ]);
    }
}
