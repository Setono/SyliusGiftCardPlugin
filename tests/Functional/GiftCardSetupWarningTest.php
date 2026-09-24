<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * The warning about a channel selling gift cards without a design is asked for several times per admin page, so the
 * answer is kept for the request. The runtime keeping it is a shared service, and under a worker runtime it outlives
 * the request, so the kernel has to reset it: once the merchant has fixed the setup, the next page must stop warning
 */
final class GiftCardSetupWarningTest extends AdminFunctionalTestCase
{
    private const TOP_BAR = '//a[@data-test-gift-card-setup-warning]';

    private const MESSAGE = '//*[@data-test-gift-card-setup-warning-message]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();

        /** @var GiftCardProductFactoryInterface $productFactory */
        $productFactory = self::getContainer()->get(GiftCardProductFactoryInterface::class);
        $this->manager->persist($productFactory->create('gift_card', 'Gift card', channels: [$this->getChannel()]));
        $this->manager->flush();
    }

    /** @test */
    public function it_names_the_channel_selling_gift_cards_without_a_design(): void
    {
        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        // The top bar of every admin page links to where designs are managed
        self::assertSame(['/admin/gift-card-designs/'], self::textsOf($response, self::TOP_BAR . '/@href'));
        self::assertStringContainsString('Test channel', implode(' ', self::textsOf($response, self::TOP_BAR)));
        // and the index that can fix it explains
        self::assertStringContainsString('Test channel', implode(' ', self::textsOf($response, self::MESSAGE)));
    }

    /** @test */
    public function it_stops_warning_on_the_next_page_once_a_design_is_there(): void
    {
        self::assertCount(1, self::textsOf($this->request('GET', '/admin/gift-cards/'), self::TOP_BAR));

        $this->persistDesign();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], self::textsOf($response, self::TOP_BAR));
        self::assertSame([], self::textsOf($response, self::MESSAGE));
    }

    private function persistDesign(): void
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode('classic');
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName('Classic');
        $design->setPosition(0);
        $design->setEnabled(true);
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();
    }
}
