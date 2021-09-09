<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Api\Admin;

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\Request;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\HttpFoundation\Request as HTTPRequest;
use Webmozart\Assert\Assert;

final class ManagingGiftCardConfigurationsContext implements Context
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
     * @When I browse gift card configurations
     */
    public function iBrowseGiftCardConfigurations(): void
    {
        $this->client->index();
    }

    /**
     * @When I want to create a new gift card configuration
     */
    public function iWantToCreateGiftCardConfiguration(): void
    {
        $this->client->buildCreateRequest();
    }

    /**
     * @When I want to update gift card configuration :code
     */
    public function iWantToUpdateGiftCardConfiguration(string $code): void
    {
        $this->client->buildUpdateRequest($code);
    }

    /**
     * @When /^I associate gift card configuration ([^"]+) to (channel "[^"]+") and locale ([^"]+)$/
     */
    public function iAssociateGiftCardConfigurationToChannelAndLocale(
        string $code,
        ChannelInterface $channel,
        string $localeCode
    ): void {
        $request = Request::customItemAction(
            'admin',
            'gift-card-configurations',
            $code,
            HTTPRequest::METHOD_PATCH,
            'associate-channel'
        );

        $request->setContent([
            'localeCode' => $localeCode,
            'channelCode' => $channel->getCode(),
        ]);

        $this->client->executeCustomRequest($request);
    }

    /**
     * @When I (try to) add it
     */
    public function iAddIt(): void
    {
        $this->client->create();
    }

    /**
     * @When I save my changes
     */
    public function iSaveMyChanges(): void
    {
        $this->client->update();
    }

    /**
     * @When I delete gift card configuration :code
     */
    public function iDeleteGiftCardConfiguration(string $code): void
    {
        $this->client->delete($code);
    }

    /**
     * @Then /^I should see a gift card configuration with code "([^"]+)"$/
     */
    public function iShouldSeeGiftCardConfiguration(string $code): void
    {
        $response = $this->client->show($code);

        Assert::same($this->responseChecker->getValue($response, 'code'), $code);
    }

    /**
     * @Then /^I should not see a gift card configuration with code "([^"]+)"$/
     */
    public function iShouldNotSeeGiftCardConfiguration(string $code): void
    {
        $response = $this->client->index();

        Assert::false(
            $this->responseChecker->hasItemWithValue($response, 'code', $code),
            sprintf('Gift card configuration with code %s still exists, but it should not', $code)
        );
    }

    /**
     * @When I specify its code as :code
     */
    public function iSpecifyItsCodeAs(string $code): void
    {
        $this->client->addRequestData('code', $code);
    }

    /**
     * @When I enable it
     */
    public function iEnableIt(): void
    {
        $this->client->addRequestData('enabled', true);
    }

    /**
     * @When I disable it
     */
    public function iDisableIt(): void
    {
        $this->client->addRequestData('enabled', false);
    }

    /**
     * @When I specify it as default configuration
     */
    public function iSpecifyItAsDefaultConfiguration(): void
    {
        $this->client->addRequestData('default', true);
    }

    /**
     * @Then It should be enabled
     */
    public function itShouldBeEnabled(): void
    {
        $response = $this->client->getLastResponse();

        Assert::true($this->responseChecker->getValue($response, 'enabled'));
    }

    /**
     * @Then It should not be enabled
     * @Then It should be disabled
     */
    public function itShouldNotBeEnabled(): void
    {
        $response = $this->client->getLastResponse();

        Assert::false($this->responseChecker->getValue($response, 'enabled'));
    }

    /**
     * @Then It should be default configuration
     */
    public function itShouldBeDefaultConfiguration(): void
    {
        $response = $this->client->getLastResponse();

        Assert::true($this->responseChecker->getValue($response, 'default'));
    }

    /**
     * @Then It should not be default configuration
     */
    public function itShouldNotBeDefaultConfiguration(): void
    {
        $response = $this->client->getLastResponse();

        Assert::false($this->responseChecker->getValue($response, 'default'));
    }

    /**
     * @Then /^It should have channel configuration for (channel "[^"]+") and locale ([^"]+)$/
     */
    public function itShouldHave(ChannelInterface $channel, string $localeCode): void
    {
        $response = $this->client->getLastResponse();

        $channelConfigurations = $this->responseChecker->getValue($response, 'channelConfigurations');
        Assert::same($channelConfigurations[0]['channel'], $this->iriConverter->getIriFromItem($channel));
        Assert::same($channelConfigurations[0]['locale'], $this->iriConverter->getIriFromResourceClass(Locale::class) . '/' . $localeCode);
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
}
