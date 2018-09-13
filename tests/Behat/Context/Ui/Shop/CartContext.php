<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Tests\Setono\SyliusGiftCardPlugin\Behat\Page\Shop\Cart\SummaryPageInterface;
use Webmozart\Assert\Assert;

final class CartContext implements Context
{
    /** @var SummaryPageInterface */
    private $summaryPage;

    public function __construct(SummaryPageInterface $summaryPage)
    {
        $this->summaryPage = $summaryPage;
    }

    /**
     * @When I use gift card with code :code
     */
    public function iUseGiftCardWithCode(string $code): void
    {
        $this->summaryPage->applyGiftCard($code);
    }

    /**
     * @Then my discount gift card should be :giftCardTotal
     */
    public function myDiscountGiftCardShouldBe(string $giftCardTotal): void
    {
        $this->summaryPage->open();

        Assert::same($this->summaryPage->getGiftCardTotal(), $giftCardTotal);
    }
}
