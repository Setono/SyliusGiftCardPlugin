<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Tests\Setono\SyliusGiftCardPlugin\Behat\Page\Admin\Product\CreateSimpleProductPageInterface;
use Webmozart\Assert\Assert;

final class ManagingGiftCardsContext implements Context
{
    /** @var CreateSimpleProductPageInterface */
    private $createGiftCardPage;

    public function __construct(
        CreateSimpleProductPageInterface $createGiftCardPage
    ) {
        $this->createGiftCardPage = $createGiftCardPage;
    }

    /**
     * @When I set its gift card value to true
     */
    public function iSetGiftCardToTrue(): void
    {
        $this->createGiftCardPage->specifyGiftCard(true);
    }

    /**
     * @Then the product :product should be a gift card
     */
    public function theProductShouldBeAGiftCard(ProductInterface $product): void
    {
        Assert::true($product->isGiftCard());
    }
}
