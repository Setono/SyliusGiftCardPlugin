<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Admin;

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Webmozart\Assert\Assert;

final class ManagingGiftCardsContext implements Context
{
    private ApiClientInterface $client;

    private ResponseCheckerInterface $responseChecker;

    private IriConverterInterface $iriConverter;

    public function __construct(
        ApiClientInterface $client,
        ResponseCheckerInterface $responseChecker,
        IriConverterInterface $iriConverter
    ) {
        $this->client = $client;
        $this->responseChecker = $responseChecker;
        $this->iriConverter = $iriConverter;
    }

    /**
     * @When I browse gift cards
     */
    public function iBrowseGiftCards(): void
    {
        $this->client->index();
    }

    /**
     * @When I want to create a new gift card
     */
    public function iWantToCreateGiftCard(): void
    {
        $this->client->buildCreateRequest();
    }

    /**
     * @When I (try to) add it
     */
    public function iAddIt(): void
    {
        $this->client->create();
    }

    /**
     * @When I open the gift card :code page
     */
    public function iOpenGiftCardPage(string $code): void
    {
        $this->client->show($code);
    }

    /**
     * @When I want to edit the gift card :code
     */
    public function iWantToEditGiftCard(string $code): void
    {
        $this->client->buildUpdateRequest($code);
    }

    /**
     * @When I save my changes
     */
    public function iSaveMyChanges(): void
    {
        $this->client->update();
    }

    /**
     * @When I delete the gift card :code
     */
    public function iDeleteGiftCard(string $code): void
    {
        $this->client->delete($code);
    }

    /**
     * @Then /^I should see a gift card with code "([^"]+)" valued at ("[^"]+")$/
     * @Then /^I should see a gift card with code "([^"]+)" valued at ("[^"]+") associated to the customer "([^"]+)"$/
     */
    public function iShouldSeeGiftCardPricedAtForCustomer(string $code, int $price, string $customerEmail = null): void
    {
        $response = $this->client->show($code);

        $giftCardPrice = $this->responseChecker->getValue($response, 'amount');
        Assert::same($price, $giftCardPrice);

        if (null !== $customerEmail) {
            $giftCardCustomer = $this->responseChecker->getValue($response, 'customer');
            Assert::same($customerEmail, $giftCardCustomer['email']);
        }
    }

    /**
     * @Then this gift card should have api origin
     */
    public function giftCardShouldHaveAPIOrigin(): void
    {
        $response = $this->client->getLastResponse();

        $origin = $this->responseChecker->getValue($response, 'origin');
        Assert::same(GiftCardInterface::ORIGIN_API, $origin);
    }

    /**
     * @Then /^I should no longer see a gift card with code "([^"]+)"$/
     */
    public function iShouldNotSeeGiftCard(string $code): void
    {
        $response = $this->client->index();

        Assert::false(
            $this->responseChecker->hasItemWithValue($response, 'code', $code),
            sprintf('Gift card with code %s still exists, but it should not', $code)
        );
    }

    /**
     * @When I do not specify its customer
     * @When I specify its customer as :customer
     */
    public function iSpecifyItsCustomerAs(?CustomerInterface $customer = null): void
    {
        $this->client->addRequestData('customer', null !== $customer ? $this->iriConverter->getIriFromItem($customer) : null);
    }

    /**
     * @When I specify its code as :code
     */
    public function iSpecifyItsCodeAs(string $code): void
    {
        $this->client->addRequestData('code', $code);
    }

    /**
     * @When /^I specify its amount as ("[^"]+")$/
     */
    public function iSpecifyItsAmountAs(string $amount): void
    {
        $this->client->addRequestData('amount', (int) $amount);
    }

    /**
     * @When I specify its currency code as :currency
     */
    public function iSpecifyItsCurrencyCodeAs(string $currencyCode): void
    {
        $this->client->addRequestData('currencyCode', $currencyCode);
    }

    /**
     * @When I specify its channel as :channel
     */
    public function iSpecifyItsChannelAs(ChannelInterface $channel): void
    {
        $this->client->addRequestData('channel', $this->iriConverter->getIriFromItem($channel));
    }

    /**
     * @When I disable it
     */
    public function iDisableIt(): void
    {
        $this->client->addRequestData('enabled', false);
    }

    /**
     * @Then I should be notified that it has been successfully created
     */
    public function iShouldBeNotifiedThatItHasBeenSuccessfullyCreated(): void
    {
        Assert::true($this->responseChecker->isCreationSuccessful($this->client->getLastResponse()));
    }

    /**
     * @Then I should be notified that it has been successfully updated
     */
    public function iShouldBeNotifiedThatItHasBeenSuccessfullyUpdated(): void
    {
        Assert::true($this->responseChecker->isUpdateSuccessful($this->client->getLastResponse()));
    }

    /**
     * @Then I should be notified that it has been successfully deleted
     */
    public function iShouldBeNotifiedThatItHasBeenSuccessfullyDelete(): void
    {
        Assert::true($this->responseChecker->isDeletionSuccessful($this->client->getLastResponse()));
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
     * @Then It should be on channel :channel
     */
    public function itShouldBeOnChannel(ChannelInterface $channel): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'channel')['code'], $channel->getCode());
    }

    /**
     * @Then It should have :currency currency
     */
    public function itShouldHaveCurrency(CurrencyInterface $currency): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'currencyCode'), $currency->getCode());
    }

    /**
     * @Then It should be disabled
     */
    public function itShouldBeDisabled(): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'enabled'), false);
    }
}
