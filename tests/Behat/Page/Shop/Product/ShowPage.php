<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Page\Shop\Product;

use Sylius\Behat\Page\Shop\Product\ShowPage as BaseShowPage;

final class ShowPage extends BaseShowPage implements ShowPageInterface
{
    public function changeAmount(string $amount): void
    {
        $this->getElement('amount')->setValue($amount);
    }

    public function defineCustomMessage(string $customMessage): void
    {
        $this->getElement('custom_message')->setValue($customMessage);
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'amount' => '[data-test-gift-card-amount-input]',
            'custom_message' => '[data-test-gift-card-custom-message-input]',
        ]);
    }
}
