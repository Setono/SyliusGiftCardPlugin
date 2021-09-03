<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Admin;

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
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
     * @When I specify its amount as :amount
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
     * @Then I should be notified that it has been successfully created
     */
    public function iShouldBeNotifiedThatItHasBeenSuccessfullyCreated(): void
    {
        Assert::true($this->responseChecker->isCreationSuccessful($this->client->getLastResponse()));
    }

    /**
     * @When I (try to) add it
     */
    public function iAddIt(): void
    {
        $this->client->create();
    }
}
