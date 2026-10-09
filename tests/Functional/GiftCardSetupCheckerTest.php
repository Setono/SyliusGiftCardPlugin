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
 * warnings about the missing and the disabled gift card payment method, which also look at the cards customers hold
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

    /**
     * A disabled gift card payment method refuses every gift card just as a missing one does (#484), so it is reported
     * while a channel sells gift cards, and the method is handed over for the warning to link to. A disabled method is
     * not a missing one, nor is a missing one disabled
     *
     * @test
     */
    public function it_reports_the_disabled_payment_method_while_a_channel_sells_gift_cards(): void
    {
        $this->createGiftCardProduct($this->getChannel(), 'CARD');
        self::assertNull($this->checker()->getDisabledPaymentMethod(), 'a missing method is not a disabled one');

        $paymentMethod = $this->createGiftCardPaymentMethod();
        self::assertNull($this->checker()->getDisabledPaymentMethod(), 'the method is created enabled');

        $paymentMethod->disable();
        $this->manager->flush();

        self::assertSame($paymentMethod, $this->checker()->getDisabledPaymentMethod());
        self::assertFalse($this->checker()->isPaymentMethodMissing());
    }

    /**
     * Nobody can buy a gift card anywhere, so there is nothing for a disabled method to refuse
     *
     * @test
     */
    public function it_has_nothing_to_say_about_a_disabled_payment_method_of_a_shop_that_does_not_sell_gift_cards(): void
    {
        $disabled = $this->createChannel('DISABLED');
        $disabled->setEnabled(false);
        $this->createGiftCardProduct($disabled, 'CARD');
        $this->createGiftCardProduct($this->getChannel(), 'DISABLED_CARD')->setEnabled(false);
        $this->createGiftCardPaymentMethod()->disable();
        $this->manager->flush();

        self::assertNull($this->checker()->getDisabledPaymentMethod());
    }

    /**
     * A shop that issues its cards in the admin sells none, and neither does a merchant who took the gift card product
     * offline, but the cards their customers hold stop working all the same while the method is missing or disabled
     *
     * @test
     */
    public function it_reports_the_payment_method_while_customers_hold_usable_gift_cards_although_none_are_for_sale(): void
    {
        $this->createEnabledGiftCard('HELD000000000001', 5000);
        $this->manager->flush();

        self::assertTrue($this->checker()->isPaymentMethodMissing());

        $paymentMethod = $this->createGiftCardPaymentMethod();
        self::assertFalse($this->checker()->isPaymentMethodMissing());
        self::assertNull($this->checker()->getDisabledPaymentMethod());

        $paymentMethod->disable();
        $this->manager->flush();

        self::assertSame($paymentMethod, $this->checker()->getDisabledPaymentMethod());
    }

    /**
     * A card nobody can spend anyway is nothing at stake
     *
     * @param 'disabled'|'spent'|'expired' $state
     *
     * @test
     *
     * @dataProvider unusableGiftCards
     */
    public function it_has_nothing_to_say_about_the_payment_method_while_customers_only_hold_gift_cards_they_cannot_spend(string $state): void
    {
        $giftCard = $this->createEnabledGiftCard('UNUSABLE00000001', 5000);
        match ($state) {
            'disabled' => $giftCard->disable(),
            'spent' => $giftCard->setAmount(0),
            'expired' => $giftCard->setExpiresAt(new \DateTimeImmutable('-1 day')),
        };
        $this->manager->flush();

        self::assertFalse($this->checker()->isPaymentMethodMissing());

        $this->createGiftCardPaymentMethod()->disable();
        $this->manager->flush();

        self::assertNull($this->checker()->getDisabledPaymentMethod());
    }

    /**
     * @return iterable<string, array{'disabled'|'spent'|'expired'}>
     */
    public static function unusableGiftCards(): iterable
    {
        yield 'disabled' => ['disabled'];
        yield 'spent' => ['spent'];
        yield 'expired' => ['expired'];
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
