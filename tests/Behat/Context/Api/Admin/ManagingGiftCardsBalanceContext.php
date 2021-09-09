<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Admin;

use Behat\Behat\Context\Context;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Webmozart\Assert\Assert;

final class ManagingGiftCardsBalanceContext implements Context
{
    private ApiClientInterface $client;

    private ResponseCheckerInterface $responseChecker;

    public function __construct(
        ApiClientInterface $client,
        ResponseCheckerInterface $responseChecker
    ) {
        $this->client = $client;
        $this->responseChecker = $responseChecker;
    }

    /**
     * @When I browse gift cards balance
     */
    public function iBrowseGiftCardsBalance(): void
    {
        $this->client->index();
    }

    /**
     * @Then /^I should see a gift card balance of ("[^"]+") in ("[^"]+" currency)$/
     */
    public function iShouldSeeGiftCardPricedAtForCustomer(int $price, CurrencyInterface $currency): void
    {
        $response = $this->client->getLastResponse();

        $items = $this->responseChecker->getCollection($response);
        foreach ($items as $item) {
            if ($item['total'] === $price) {
                Assert::same($item['currencyCode'], $currency->getCode());

                return;
            }
        }

        Assert::false(true);
    }
}
