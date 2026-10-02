<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Checker\GiftCardSetupCheckerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * The warning about a channel selling gift cards without a design must only ever point at channels where that
 * is actually the case, so every combination of product and design state is walked through here. The same goes for the
 * warning about the missing gift card payment method
 */
final class GiftCardSetupCheckerTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_has_nothing_to_say_about_a_channel_that_does_not_sell_gift_cards(): void
    {
        $this->getChannel();

        self::assertSame([], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_names_a_channel_selling_gift_cards_without_an_enabled_design(): void
    {
        $this->createGiftCardProduct($this->getChannel(), 'CARD');

        self::assertSame([$this->getChannel()->getCode()], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_is_satisfied_by_an_enabled_design_in_the_channel(): void
    {
        $this->createGiftCardProduct($this->getChannel(), 'CARD');
        $this->createDesign($this->getChannel(), true);

        self::assertSame([], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_does_not_count_a_disabled_design(): void
    {
        $this->createGiftCardProduct($this->getChannel(), 'CARD');
        $this->createDesign($this->getChannel(), false);

        self::assertSame([$this->getChannel()->getCode()], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_does_not_count_a_design_enabled_for_another_channel(): void
    {
        $other = $this->createChannel('OTHER');
        $this->createGiftCardProduct($this->getChannel(), 'CARD');
        $this->createDesign($other, true);

        self::assertSame([$this->getChannel()->getCode()], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_ignores_a_gift_card_product_that_is_disabled(): void
    {
        $product = $this->createGiftCardProduct($this->getChannel(), 'CARD');
        $product->setEnabled(false);
        $this->manager->flush();

        self::assertSame([], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_ignores_an_ordinary_product(): void
    {
        $product = $this->createGiftCardProduct($this->getChannel(), 'MUG');
        $product->setGiftCard(false);
        $this->manager->flush();

        self::assertSame([], $this->codesOfChannelsWithoutDesign());
    }

    /**
     * Nobody can buy anything in a disabled channel, so there is nothing to warn about there yet
     *
     * @test
     */
    public function it_ignores_a_disabled_channel(): void
    {
        $disabled = $this->createChannel('DISABLED');
        $disabled->setEnabled(false);
        $this->createGiftCardProduct($this->getChannel(), 'CARD', $disabled);

        self::assertSame([$this->getChannel()->getCode()], $this->codesOfChannelsWithoutDesign());
    }

    /** @test */
    public function it_reports_every_such_channel(): void
    {
        $other = $this->createChannel('OTHER');
        $this->createGiftCardProduct($this->getChannel(), 'CARD', $other);

        self::assertSame(['OTHER', $this->getChannel()->getCode()], $this->codesOfChannelsWithoutDesign());
    }

    /**
     * A shop that sells gift cards refuses every one of them until the payment method gift card payments are made
     * with exists
     *
     * @test
     */
    public function it_reports_the_missing_payment_method_while_a_channel_sells_gift_cards(): void
    {
        $this->createGiftCardProduct($this->getChannel(), 'CARD');

        self::assertTrue($this->checker()->isPaymentMethodMissing());

        $this->createGiftCardPaymentMethod();

        self::assertFalse($this->checker()->isPaymentMethodMissing());
    }

    /** @test */
    public function it_has_nothing_to_say_about_the_payment_method_of_a_shop_that_does_not_sell_gift_cards(): void
    {
        $this->getChannel();
        $this->createGiftCardProduct($this->getChannel(), 'CARD')->setEnabled(false);
        $this->manager->flush();

        self::assertFalse($this->checker()->isPaymentMethodMissing());
    }

    /**
     * Nobody can buy anything in a disabled channel, so it does not need the payment method yet either
     *
     * @test
     */
    public function it_has_nothing_to_say_about_the_payment_method_while_only_a_disabled_channel_sells_gift_cards(): void
    {
        $this->getChannel();
        $disabled = $this->createChannel('DISABLED');
        $disabled->setEnabled(false);
        $this->createGiftCardProduct($disabled, 'CARD');

        self::assertFalse($this->checker()->isPaymentMethodMissing());
    }

    private function checker(): GiftCardSetupCheckerInterface
    {
        /** @var GiftCardSetupCheckerInterface $checker */
        $checker = self::getContainer()->get(GiftCardSetupCheckerInterface::class);

        return $checker;
    }

    /**
     * @return list<string|null>
     */
    private function codesOfChannelsWithoutDesign(): array
    {
        /** @var GiftCardSetupCheckerInterface $checker */
        $checker = self::getContainer()->get(GiftCardSetupCheckerInterface::class);

        $codes = array_map(static fn (ChannelInterface $channel): ?string => $channel->getCode(), $checker->getChannelsWithoutDesign());
        sort($codes);

        return $codes;
    }

    private function createGiftCardProduct(ChannelInterface $channel, string $code, ChannelInterface ...$moreChannels): Product
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode($code);
        $product->setName($code);
        $product->setSlug(strtolower($code));
        $product->setGiftCard(true);
        $product->setEnabled(true);
        $product->addChannel($channel);
        foreach ($moreChannels as $moreChannel) {
            $product->addChannel($moreChannel);
        }
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode($code . '_VARIANT');
        $variant->setProduct($product);
        $this->manager->persist($variant);

        $this->manager->flush();

        return $product;
    }

    private function createDesign(ChannelInterface $channel, bool $enabled): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $designFactory */
        $designFactory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $designFactory->createNew();
        $design->setCode('design-' . strtolower((string) $channel->getCode()) . '-' . ($enabled ? 'on' : 'off'));
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName('Design');
        $design->setPosition(0);
        $design->setEnabled($enabled);
        $design->addChannel($channel);

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }
}
