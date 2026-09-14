<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\PreviewGiftCardDesignPdfAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The plugin does not depend on symfony/browser-kit, so this exercises the real, container-wired action
 * directly rather than through a WebTestCase/KernelBrowser: it proves the "sylius.repository.channel"
 * argument wiring in controller.xml is correct and that, on a shop with more than one channel, the preview
 * resolves the design's own channel rather than depending on any request hostname
 */
final class PreviewGiftCardDesignPdfActionTest extends GiftCardFunctionalTestCase
{
    private PreviewGiftCardDesignPdfAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var PreviewGiftCardDesignPdfAction $action */
        $action = self::getContainer()->get(PreviewGiftCardDesignPdfAction::class);
        $this->action = $action;
    }

    /** @test */
    public function it_previews_a_design_on_a_multi_channel_shop_for_the_designs_own_channel(): void
    {
        $currency = $this->createCurrency('USD');
        $locale = $this->createLocale('en_US');

        // Neither channel's hostname is ever consulted: the design belongs to SECOND, so that is the one
        // the preview must be rendered for, regardless of which channel a request happened to arrive on
        $this->createChannel('FIRST', $currency, $locale);
        $second = $this->createChannel('SECOND', $currency, $locale);
        $design = $this->createDesign($second);

        $this->manager->flush();

        $response = ($this->action)(new Request(), (int) $design->getId());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    /** @test */
    public function it_falls_back_to_a_shop_channel_when_the_design_has_none_of_its_own(): void
    {
        $currency = $this->createCurrency('USD');
        $locale = $this->createLocale('en_US');
        $this->createChannel('ONLY', $currency, $locale);

        $design = $this->createDesign();

        $this->manager->flush();

        $response = ($this->action)(new Request(), (int) $design->getId());

        self::assertSame(200, $response->getStatusCode());
    }

    /** @test */
    public function it_renders_the_requested_channel_from_the_query_parameter(): void
    {
        $currency = $this->createCurrency('USD');
        $locale = $this->createLocale('en_US');
        $first = $this->createChannel('FIRST', $currency, $locale);
        $second = $this->createChannel('SECOND', $currency, $locale);
        $design = $this->createDesign($first, $second);

        $this->manager->flush();

        $response = ($this->action)(new Request(['channel' => 'SECOND']), (int) $design->getId());

        self::assertSame(200, $response->getStatusCode());
    }

    /** @test */
    public function it_rejects_a_channel_the_design_is_not_assigned_to(): void
    {
        $currency = $this->createCurrency('USD');
        $locale = $this->createLocale('en_US');
        $first = $this->createChannel('FIRST', $currency, $locale);
        $this->createChannel('OTHER', $currency, $locale);
        $design = $this->createDesign($first);

        $this->manager->flush();

        $this->expectException(NotFoundHttpException::class);

        ($this->action)(new Request(['channel' => 'OTHER']), (int) $design->getId());
    }

    private function createCurrency(string $code): CurrencyInterface
    {
        $currency = new Currency();
        $currency->setCode($code);
        $this->manager->persist($currency);

        return $currency;
    }

    private function createLocale(string $code): LocaleInterface
    {
        $locale = new Locale();
        $locale->setCode($code);
        $this->manager->persist($locale);

        return $locale;
    }

    private function createChannel(string $code, CurrencyInterface $currency, LocaleInterface $locale): ChannelInterface
    {
        /** @var ChannelFactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = self::getContainer()->get('sylius.factory.channel');

        /** @var ChannelInterface $channel */
        $channel = $channelFactory->createNamed($code);
        $channel->setCode($code);
        $channel->setBaseCurrency($currency);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->addLocale($locale);

        $this->manager->persist($channel);

        return $channel;
    }

    private function createDesign(ChannelInterface ...$channels): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $designFactory */
        $designFactory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $designFactory->createNew();
        $design->setCode('classic-' . spl_object_id($design));
        foreach ($channels as $channel) {
            $design->addChannel($channel);
        }

        $this->manager->persist($design);

        return $design;
    }
}
