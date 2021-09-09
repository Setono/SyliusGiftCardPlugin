<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Shop;

use Behat\Behat\Context\Context;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\Request;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\HttpFoundation\Request as HTTPRequest;
use Webmozart\Assert\Assert;

final class ManagingGiftCardsContext implements Context
{
    private ApiClientInterface $client;

    private ResponseCheckerInterface $responseChecker;

    private SharedStorageInterface $sharedStorage;

    public function __construct(
        ApiClientInterface $client,
        ResponseCheckerInterface $responseChecker,
        SharedStorageInterface $sharedStorage
    ) {
        $this->client = $client;
        $this->responseChecker = $responseChecker;
        $this->sharedStorage = $sharedStorage;
    }

    /**
     * @When I browse gift cards
     */
    public function iBrowseGiftCards(): void
    {
        $this->client->index();
    }

    /**
     * @When I open the gift card :code page
     */
    public function iOpenGiftCardPage(string $code): void
    {
        $this->client->show($code);
    }

    /**
     * @Given I apply gift card with code :code
     */
    public function iApplyGiftCardToOrder(string $code): void
    {
        $this->applyGiftCardToOrder($code);
    }

    /**
     * @Then /^Gift cards list should contain a gift card with code "([^"]+)"$/
     */
    public function giftCardsListShouldContain(string $code): void
    {
        $response = $this->client->index();

        Assert::notEmpty($this->responseChecker->getCollectionItemsWithValue($response, 'code', $code));
    }

    /**
     * @Then /^Gift cards list should not contain a gift card with code "([^"]+)"$/
     */
    public function giftCardsListShouldNotContain(string $code): void
    {
        $response = $this->client->index();

        Assert::isEmpty($this->responseChecker->getCollectionItemsWithValue($response, 'code', $code));
    }

    /**
     * @Then /^It should be valued at ("[^"]+")$/
     */
    public function itShouldBeValuedAt(int $amount): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'amount'), $amount);
    }

    /**
     * @Then /^It should be initially valued at ("[^"]+")$/
     */
    public function itShouldBeInitiallyValuedAt(int $amount): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'initialAmount'), $amount);
    }

    /**
     * @Then It should have :currency currency
     */
    public function itShouldHaveCurrency(CurrencyInterface $currency): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'currencyCode'), $currency->getCode());
    }

    /**
     * @Then the gift card :code should be disabled
     */
    public function theGiftCardShouldBeDisabled(string $code): void
    {
        $this->client->show($code);

        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'enabled'), false);
    }

    private function applyGiftCardToOrder(string $giftCardCode): void
    {
        $request = Request::customItemAction(
            'shop',
            'gift-cards',
            $giftCardCode,
            HTTPRequest::METHOD_PATCH,
            'add-to-order'
        );

        $request->setContent(['orderTokenValue' => $this->sharedStorage->get('cart_token')]);

        $this->client->executeCustomRequest($request);
    }
}
