<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Payum\Core\Model\GatewayConfigInterface;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodNotFoundException;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The payment method gift card payments are made with used to be created the first time a customer paid with a gift
 * card, flushing whatever else was pending in the middle of placing the order. It is now set up once, by a console
 * command (or the fixture, which does the same), and the provider only finds it
 */
final class GiftCardPaymentMethodSetupTest extends GiftCardFunctionalTestCase
{
    use LoadsFixturesTrait;

    /** @test */
    public function the_provider_finds_no_payment_method_until_the_shop_has_set_it_up(): void
    {
        $this->getChannel();

        self::assertNull($this->provider()->findPaymentMethod());

        $this->expectException(GiftCardPaymentMethodNotFoundException::class);
        $this->expectExceptionMessage('setono:gift-card:create-payment-method');

        $this->provider()->getEnabledPaymentMethod();
    }

    /**
     * Gift card payments are made with the method in every channel, whichever channels it is in, so it is created in
     * none of the shop's channels (#411)
     *
     * @test
     */
    public function the_command_creates_an_offline_payment_method_in_no_channel(): void
    {
        $this->getChannel();
        $this->createChannel('SECOND_CHANNEL');

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Created the gift card payment method "gift_card"', $tester->getDisplay());

        $this->manager->clear();

        // gateway_name is a NOT NULL column, so createWithGateway() alone (which only sets the factory name) would
        // have failed the command's flush
        $paymentMethod = $this->provider()->getEnabledPaymentMethod();
        self::assertSame('gift_card', $paymentMethod->getCode());
        self::assertTrue($paymentMethod->isEnabled());
        self::assertCount(0, $paymentMethod->getChannels());

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        self::assertInstanceOf(GatewayConfigInterface::class, $gatewayConfig);
        self::assertSame('offline', $gatewayConfig->getFactoryName());
        self::assertSame('gift_card', $gatewayConfig->getGatewayName());
    }

    /** @test */
    public function the_command_leaves_an_existing_payment_method_alone(): void
    {
        $this->getChannel();

        $this->runCommand();
        $this->manager->clear();
        $created = $this->provider()->getEnabledPaymentMethod();

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The gift card payment method "gift_card" already exists', $tester->getDisplay());

        self::assertCount(1, $this->paymentMethodRepository()->findBy(['code' => 'gift_card']));
        self::assertSame($created->getId(), $this->provider()->getEnabledPaymentMethod()->getId());
    }

    /**
     * An administrator may have disabled the method on purpose, to stop gift cards being redeemed for a while (#484), so
     * a deploy running the command leaves it disabled, and only says so
     *
     * @test
     */
    public function the_command_leaves_a_disabled_payment_method_disabled_and_says_so(): void
    {
        $this->createGiftCardPaymentMethod()->disable();
        $this->manager->flush();
        $this->manager->clear();

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('The gift card payment method "gift_card" already exists', $display);
        self::assertStringContainsString('The gift card payment method "gift_card" is disabled, so the shop refuses gift cards until it is enabled again in the admin', $display);

        $this->manager->clear();
        self::assertCount(1, $this->paymentMethodRepository()->findBy(['code' => 'gift_card']));
        $paymentMethod = $this->provider()->findPaymentMethod();
        self::assertInstanceOf(PaymentMethodInterface::class, $paymentMethod);
        self::assertFalse($paymentMethod->isEnabled());
        self::assertNull($this->provider()->findEnabledPaymentMethod());
    }

    /** @test */
    public function the_command_says_nothing_about_an_enabled_payment_method_being_disabled(): void
    {
        $this->createGiftCardPaymentMethod();
        $this->manager->clear();

        self::assertStringNotContainsString('disabled', $this->runCommand()->getDisplay());
    }

    /**
     * The payment method's name is what the order pages, the customer's account and the admin show for a gift card
     * payment, so the method is named in the language of every locale of the shop, not in English for all of them
     *
     * @test
     */
    public function the_payment_method_is_named_in_the_language_of_every_locale_of_the_shop(): void
    {
        $this->getChannel();

        $danish = new Locale();
        $danish->setCode('da_DK');
        $this->manager->persist($danish);

        // a locale of the shop that no channel has as its default
        $french = new Locale();
        $french->setCode('fr_FR');
        $this->manager->persist($french);

        $danishChannel = $this->createChannel('DANISH_CHANNEL');
        $danishChannel->addLocale($danish);
        $danishChannel->setDefaultLocale($danish);
        $this->manager->flush();

        $this->runCommand();
        $this->manager->clear();

        $paymentMethod = $this->provider()->getEnabledPaymentMethod();

        $names = [];
        foreach ($paymentMethod->getTranslations()->getKeys() as $localeCode) {
            $names[(string) $localeCode] = $paymentMethod->getTranslation((string) $localeCode)->getName();
        }
        ksort($names);

        self::assertSame(['da_DK' => 'Gavekort', 'en_US' => 'Gift card', 'fr_FR' => 'Chèque-cadeau'], $names);
    }

    /**
     * A shop seeded with fixtures takes gift cards straight away: the bundled suite sets the method up, in no channel
     * like the command does, and a second load leaves it alone
     *
     * @test
     */
    public function the_fixture_sets_the_payment_method_up_once(): void
    {
        $this->getChannel();

        $this->loadFixture('setono_gift_card_payment_method', []);
        $this->loadFixture('setono_gift_card_payment_method', []);
        $this->manager->clear();

        self::assertCount(1, $this->paymentMethodRepository()->findBy(['code' => 'gift_card']));
        $paymentMethod = $this->provider()->findPaymentMethod();
        self::assertInstanceOf(PaymentMethodInterface::class, $paymentMethod);
        self::assertCount(0, $paymentMethod->getChannels());
    }

    /**
     * A method created before #411 is in the channels that existed back then, and one an administrator set up by hand
     * is in whichever channels they chose. Nothing takes them away: the command leaves an existing method as it is,
     * channels included
     *
     * @test
     */
    public function the_command_leaves_the_channels_of_an_existing_payment_method_alone(): void
    {
        $this->runCommand();
        $this->manager->clear();
        $this->provider()->getEnabledPaymentMethod()->addChannel($this->getChannel());
        $this->manager->flush();
        $this->manager->clear();

        $this->runCommand();
        $this->manager->clear();

        $channelCodes = [];
        foreach ($this->provider()->getEnabledPaymentMethod()->getChannels() as $channel) {
            $channelCodes[] = $channel->getCode();
        }

        self::assertSame(['TEST_CHANNEL'], $channelCodes);
    }

    private function runCommand(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $tester = new CommandTester((new Application($kernel))->find('setono:gift-card:create-payment-method'));
        $tester->execute([]);

        return $tester;
    }

    private function provider(): GiftCardPaymentMethodProviderInterface
    {
        /** @var GiftCardPaymentMethodProviderInterface $provider */
        $provider = self::getContainer()->get(GiftCardPaymentMethodProviderInterface::class);

        return $provider;
    }

    /**
     * @return PaymentMethodRepositoryInterface<PaymentMethodInterface>
     */
    private function paymentMethodRepository(): PaymentMethodRepositoryInterface
    {
        /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.payment_method');

        return $repository;
    }
}
