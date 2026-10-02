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

        $this->provider()->getPaymentMethod();
    }

    /** @test */
    public function the_command_creates_an_offline_payment_method_in_every_channel(): void
    {
        $channel = $this->getChannel();
        $second = $this->createChannel('SECOND_CHANNEL');

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Created the gift card payment method "gift_card"', $tester->getDisplay());

        $this->manager->clear();

        // gateway_name is a NOT NULL column, so createWithGateway() alone (which only sets the factory name) would
        // have failed the command's flush
        $paymentMethod = $this->provider()->getPaymentMethod();
        self::assertSame('gift_card', $paymentMethod->getCode());
        self::assertTrue($paymentMethod->isEnabled());
        self::assertEqualsCanonicalizing(
            [$channel->getCode(), $second->getCode()],
            array_map(static fn ($channel): ?string => $channel->getCode(), $paymentMethod->getChannels()->toArray()),
        );

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
        $created = $this->provider()->getPaymentMethod();

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The gift card payment method "gift_card" already exists', $tester->getDisplay());

        self::assertCount(1, $this->paymentMethodRepository()->findBy(['code' => 'gift_card']));
        self::assertSame($created->getId(), $this->provider()->getPaymentMethod()->getId());
    }

    /**
     * The payment method's name is what the order pages show for a gift card payment, so the method is named in the
     * language of every channel rather than in a locale a channel may not have
     *
     * @test
     */
    public function the_payment_method_is_named_in_the_default_locale_of_every_channel(): void
    {
        $this->getChannel();

        $danish = new Locale();
        $danish->setCode('da_DK');
        $this->manager->persist($danish);

        $danishChannel = $this->createChannel('DANISH_CHANNEL');
        $danishChannel->addLocale($danish);
        $danishChannel->setDefaultLocale($danish);
        $this->manager->flush();

        $this->runCommand();
        $this->manager->clear();

        $paymentMethod = $this->provider()->getPaymentMethod();
        self::assertEqualsCanonicalizing(['en_US', 'da_DK'], array_keys($paymentMethod->getTranslations()->toArray()));
        self::assertSame('Gift card', $paymentMethod->getTranslation('da_DK')->getName());
    }

    /**
     * A shop seeded with fixtures takes gift cards straight away: the bundled suite sets the method up, and a second
     * load leaves it alone
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
        self::assertInstanceOf(PaymentMethodInterface::class, $this->provider()->findPaymentMethod());
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
