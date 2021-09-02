<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Admin;

use Behat\Behat\Context\Context;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Webmozart\Assert\Assert;

final class ManagingGiftCardsContext implements Context
{
    private ApiClientInterface $client;

    private ResponseCheckerInterface $responseChecker;

    public function __construct(ApiClientInterface $client, ResponseCheckerInterface $responseChecker)
    {
        $this->client = $client;
        $this->responseChecker = $responseChecker;
    }

    /**
     * @When I browse gift cards
     */
    public function iBrowseGiftCards(): void
    {
        $this->client->index();
    }

    /**
     * @Then /^I should see a gift card with code "([^"]+)" valued at ("[^"]+")$/
     */
    public function iShouldSeeGiftCardPricedAt(string $code, int $price): void
    {
        $response = $this->client->show($code);

        $giftCardPrice = $this->responseChecker->getValue($response, 'amount');
        Assert::same($price, $giftCardPrice);
    }
}
