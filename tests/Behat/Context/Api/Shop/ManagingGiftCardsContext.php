<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Shop;

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
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
}
