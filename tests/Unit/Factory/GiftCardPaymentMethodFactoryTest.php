<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardPaymentMethodFactory;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Locale\Model\Locale;

/**
 * The payment method gift card payments are made with is set up once, by a command or a fixture, rather than created
 * the first time a customer redeems a card, so it is made out for the whole shop: every channel, named in the
 * language of each
 */
final class GiftCardPaymentMethodFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_creates_an_enabled_offline_payment_method_with_the_configured_code(): void
    {
        $paymentMethod = $this->factory([])->create();

        self::assertSame('gift_card', $paymentMethod->getCode());
        self::assertTrue($paymentMethod->isEnabled());
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        self::assertNotNull($gatewayConfig);
        self::assertSame('offline', $gatewayConfig->getFactoryName());
        // gatewayName is a NOT NULL column, which createWithGateway() leaves empty
        self::assertSame('gift_card', $gatewayConfig->getGatewayName());
    }

    /** @test */
    public function it_makes_the_payment_method_available_in_every_channel_named_in_the_default_locale_of_each(): void
    {
        $web = $this->channel('WEB', 'en_US');
        $danish = $this->channel('DANISH', 'da_DK');
        $alsoEnglish = $this->channel('MOBILE', 'en_US');

        $paymentMethod = $this->factory([$web, $danish, $alsoEnglish])->create();

        self::assertSame([$web, $danish, $alsoEnglish], array_values($paymentMethod->getChannels()->toArray()));
        self::assertEqualsCanonicalizing(['en_US', 'da_DK'], array_keys($paymentMethod->getTranslations()->toArray()));
        self::assertSame('Gift card', $paymentMethod->getTranslation('da_DK')->getName());
        self::assertSame('Gift card', $paymentMethod->getTranslation('en_US')->getName());
    }

    /** @test */
    public function it_names_the_payment_method_in_en_us_while_the_shop_has_no_channel(): void
    {
        $paymentMethod = $this->factory([])->create();

        self::assertCount(0, $paymentMethod->getChannels());
        self::assertSame(['en_US'], array_keys($paymentMethod->getTranslations()->toArray()));
        self::assertSame('Gift card', $paymentMethod->getTranslation('en_US')->getName());
    }

    /**
     * @param list<ChannelInterface> $channels
     */
    private function factory(array $channels): GiftCardPaymentMethodFactory
    {
        $paymentMethodFactory = $this->prophesize(PaymentMethodFactoryInterface::class);
        $paymentMethodFactory->createWithGateway('offline')->will(static function (): PaymentMethodInterface {
            $gatewayConfig = new GatewayConfig();
            $gatewayConfig->setFactoryName('offline');

            $paymentMethod = new PaymentMethod();
            $paymentMethod->setGatewayConfig($gatewayConfig);

            return $paymentMethod;
        });

        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepository->findAll()->willReturn($channels);

        return new GiftCardPaymentMethodFactory($paymentMethodFactory->reveal(), $channelRepository->reveal(), 'gift_card');
    }

    private function channel(string $code, string $defaultLocaleCode): ChannelInterface
    {
        $locale = new Locale();
        $locale->setCode($defaultLocaleCode);

        $channel = new Channel();
        $channel->setCode($code);
        $channel->setDefaultLocale($locale);

        return $channel;
    }
}
