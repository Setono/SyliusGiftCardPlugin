<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Page\Shop\Cart;

use Sylius\Behat\Page\Shop\Cart\SummaryPage as BaseSummaryPage;

final class SummaryPage extends BaseSummaryPage implements SummaryPageInterface
{
    public function applyGiftCard(string $giftCardCode): void
    {
        $this->getElement('gift_card_field')->setValue($giftCardCode);
        $this->getElement('apply_gift_card_button')->press();
    }

    public function getGiftCardTotal(): string
    {
        $giftCardTotalElement = $this->getElement('gift_card_total');

        return $giftCardTotalElement->getText();
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'apply_gift_card_button' => 'button[form=setono-sylius-gift-card-add-gift-card-to-order]',
            'gift_card_field' => 'input[form=setono-sylius-gift-card-add-gift-card-to-order]',
            'gift_card_total' => '#setono-cart-gift-card-total',
        ]);
    }
}
