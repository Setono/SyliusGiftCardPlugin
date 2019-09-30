<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Behat\Page\Admin\Product\IndexPageInterface;
use Sylius\Behat\Service\Resolver\CurrentPageResolverInterface;
use Tests\Setono\SyliusGiftCardPlugin\Behat\Page\Admin\Product\CreateGiftCardPageInterface;
use Webmozart\Assert\Assert;

final class ManagingGiftCardsContext implements Context
{
    /** @var CreateGiftCardPageInterface */
    private $createGiftCardPage;

    /** @var CurrentPageResolverInterface */
    private $currentPageResolver;

    /** @var IndexPageInterface */
    private $indexPage;

    public function __construct(
        CreateGiftCardPageInterface $createGiftCardPage,
        CurrentPageResolverInterface $currentPageResolver,
        IndexPageInterface $indexPage
    ) {
        $this->createGiftCardPage = $createGiftCardPage;
        $this->currentPageResolver = $currentPageResolver;
        $this->indexPage = $indexPage;
    }

    /**
     * @Given I want to create a new gift card
     */
    public function iWantToCreateANewGiftCard(): void
    {
        $this->createGiftCardPage->open();
    }

    /**
     * @When I specify its code as :code
     * @When I do not specify its code
     */
    public function iSpecifyItsCodeAs(string $code = null): void
    {
        $currentPage = $this->resolveCurrentPage();

        $currentPage->specifyCode($code);
    }

    /**
     * @When I name it :name in :language
     * @When I rename it to :name in :language
     */
    public function iRenameItToIn(string $name, string $language): void
    {
        $currentPage = $this->resolveCurrentPage();

        $currentPage->nameItIn($name, $language);
    }

    /**
     * @When I set its slug to :slug
     * @When I set its slug to :slug in :language
     * @When I remove its slug
     */
    public function iSetItsSlugToIn(string $slug = null, string $language = 'en_US'): void
    {
        $this->createGiftCardPage->specifySlugIn($slug, $language);
    }

    /**
     * @When /^I set its(?:| default) price to "(?:€|£|\$)([^"]+)" for "([^"]+)" channel$/
     */
    public function iSetItsPriceTo(string $price, string $channelName): void
    {
        $this->createGiftCardPage->specifyPrice($channelName, $price);
    }

    /**
     * @When I add it
     * @When I try to add it
     */
    public function iAddIt(): void
    {
        $this->createGiftCardPage->create();
    }

    /**
     * @Then I should see the product :productName in the list
     * @Then the product :productName should appear in the store
     * @Then the product :productName should be in the shop
     * @Then this product should still be named :productName
     */
    public function theProductShouldAppearInTheShop(string $productName): void
    {
        $this->iWantToBrowseProducts();

        Assert::true($this->indexPage->isSingleResourceOnPage(['name' => $productName]));
    }

    /**
     * @Given I am browsing products
     * @When I browse products
     * @When I want to browse products
     */
    public function iWantToBrowseProducts(): void
    {
        $this->indexPage->open();
    }

    /**
     * @Then the product :product should be a gift card
     */
    public function theProductShouldBeAGiftCard(ProductInterface $product): void
    {
        Assert::true($product->isGiftCard());
    }

    /**
     * @return CreateGiftCardPageInterface|SymfonyPageInterface
     */
    private function resolveCurrentPage()
    {
        return $this->currentPageResolver->getCurrentPageWithForm([
            $this->createGiftCardPage,
        ]);
    }
}
