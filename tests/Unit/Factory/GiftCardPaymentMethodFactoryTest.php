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
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * The payment method gift card payments are made with is set up once, by a command or a fixture, rather than created
 * the first time a customer redeems a card, so it is made out for the whole shop: every channel, named in the
 * language of every locale of the shop
 */
final class GiftCardPaymentMethodFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_creates_an_enabled_offline_payment_method_with_the_configured_code(): void
    {
        $paymentMethod = $this->factory([], [])->create();

        self::assertSame('gift_card', $paymentMethod->getCode());
        self::assertTrue($paymentMethod->isEnabled());
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        self::assertNotNull($gatewayConfig);
        self::assertSame('offline', $gatewayConfig->getFactoryName());
        // gatewayName is a NOT NULL column, which createWithGateway() leaves empty
        self::assertSame('gift_card', $gatewayConfig->getGatewayName());
    }

    /** @test */
    public function it_makes_the_payment_method_available_in_every_channel(): void
    {
        $web = $this->channel('WEB', 'en_US');
        $danish = $this->channel('DANISH', 'da_DK');
        $alsoEnglish = $this->channel('MOBILE', 'en_US');

        $paymentMethod = $this->factory([$web, $danish, $alsoEnglish], ['en_US', 'da_DK'])->create();

        self::assertSame([$web, $danish, $alsoEnglish], array_values($paymentMethod->getChannels()->toArray()));
    }

    /**
     * The name is what the order pages, the customer's account and the admin show for a gift card payment, so it is
     * given in the language of every locale of the shop, a locale no channel has as its default included. Each locale
     * gets a translation of its own: had the fallback locale stayed behind, naming a locale without a translation yet
     * would rename the fallback's instead
     *
     * @test
     */
    public function it_names_the_payment_method_in_the_language_of_every_locale_of_the_shop(): void
    {
        $paymentMethod = $this->factory(
            [$this->channel('WEB', 'en_US'), $this->channel('DANISH', 'da_DK')],
            ['en_US', 'da_DK', 'fr_FR'],
        )->create();

        $names = [];
        foreach ($paymentMethod->getTranslations()->getKeys() as $localeCode) {
            $names[(string) $localeCode] = $paymentMethod->getTranslation((string) $localeCode)->getName();
        }
        ksort($names);

        self::assertSame(['da_DK' => 'Gavekort', 'en_US' => 'Gift card', 'fr_FR' => 'Chèque-cadeau'], $names);

        // left in the default locale of the first channel
        self::assertSame('Gift card', $paymentMethod->getName());
    }

    /** @test */
    public function it_names_the_payment_method_in_en_us_while_the_shop_has_no_channel_or_locale(): void
    {
        $paymentMethod = $this->factory([], [])->create();

        self::assertCount(0, $paymentMethod->getChannels());
        self::assertSame(['en_US'], array_keys($paymentMethod->getTranslations()->toArray()));
        self::assertSame('Gift card', $paymentMethod->getTranslation('en_US')->getName());
    }

    /**
     * @param list<ChannelInterface> $channels
     * @param list<string> $localeCodes the locales of the shop
     */
    private function factory(array $channels, array $localeCodes): GiftCardPaymentMethodFactory
    {
        $paymentMethodFactory = $this->prophesize(PaymentMethodFactoryInterface::class);
        $paymentMethodFactory->createWithGateway('offline')->will(static function (): PaymentMethodInterface {
            $gatewayConfig = new GatewayConfig();
            $gatewayConfig->setFactoryName('offline');

            // Sylius' payment method factory is translatable: it starts the method out in the shop's default locale,
            // which is also its fallback
            $paymentMethod = new PaymentMethod();
            $paymentMethod->setCurrentLocale('en_US');
            $paymentMethod->setFallbackLocale('en_US');
            $paymentMethod->setGatewayConfig($gatewayConfig);

            return $paymentMethod;
        });

        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepository->findAll()->willReturn($channels);

        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $localeRepository->findAll()->willReturn(array_map(static function (string $localeCode): LocaleInterface {
            $locale = new Locale();
            $locale->setCode($localeCode);

            return $locale;
        }, $localeCodes));

        return new GiftCardPaymentMethodFactory(
            $paymentMethodFactory->reveal(),
            $channelRepository->reveal(),
            'gift_card',
            $this->translator(),
            $localeRepository->reveal(),
        );
    }

    /**
     * The plugin's own translations, so the names are the ones a shop gets
     */
    private function translator(): Translator
    {
        $translator = new Translator('en_US');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['en', 'da', 'fr'] as $locale) {
            $translator->addResource('yaml', sprintf('%s/../../../src/Resources/translations/messages.%s.yml', __DIR__, $locale), $locale);
        }

        return $translator;
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
