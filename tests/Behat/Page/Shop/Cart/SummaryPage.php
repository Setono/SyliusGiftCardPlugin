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
            'apply_gift_card_button' => 'button:contains("Apply gift card")',
            'gift_card_field' => '#setono-gift-card input',
            'gift_card_validation_message' => '#setono-gift-card .sylius-validation-error',
            'gift_card_total' => '#setono-cart-gift-card-total',
        ]);
    }
}
