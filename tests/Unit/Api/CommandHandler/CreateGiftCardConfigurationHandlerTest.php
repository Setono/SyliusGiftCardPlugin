<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\CommandHandler;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Command\CreateGiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Api\CommandHandler\CreateGiftCardConfigurationHandler;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

final class CreateGiftCardConfigurationHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $handler = new CreateGiftCardConfigurationHandler(
            $this->prophesize(GiftCardConfigurationFactoryInterface::class)->reveal(),
            $this->prophesize(ObjectManager::class)->reveal(),
        );

        $this->assertInstanceOf(CreateGiftCardConfigurationHandler::class, $handler);
    }

    /**
     * @test
     */
    public function it_creates_gift_card_configuration(): void
    {
        $giftCardConfigurationFactory = $this->prophesize(GiftCardConfigurationFactoryInterface::class);
        $giftCardConfigurationManager = $this->prophesize(ObjectManager::class);

        $giftCardConfiguration = $this->prophesize(GiftCardConfigurationInterface::class);

        $giftCardConfigurationFactory->createNew()->willReturn($giftCardConfiguration->reveal());

        $handler = new CreateGiftCardConfigurationHandler(
            $giftCardConfigurationFactory->reveal(),
            $giftCardConfigurationManager->reveal()
        );

        $giftCardConfigurationManager->persist($giftCardConfiguration->reveal())->shouldBeCalled();

        $command = new CreateGiftCardConfiguration('test', false, false);
        $handler->__invoke($command);
    }

    /**
     * @test
     */
    public function it_does_not_set_values_if_null(): void
    {
        $giftCardConfigurationFactory = $this->prophesize(GiftCardConfigurationFactoryInterface::class);
        $giftCardConfigurationManager = $this->prophesize(ObjectManager::class);

        $giftCardConfiguration = $this->prophesize(GiftCardConfigurationInterface::class);

        $giftCardConfigurationFactory->createNew()->willReturn($giftCardConfiguration->reveal());

        $handler = new CreateGiftCardConfigurationHandler(
            $giftCardConfigurationFactory->reveal(),
            $giftCardConfigurationManager->reveal()
        );

        $giftCardConfigurationManager->persist($giftCardConfiguration->reveal())->shouldBeCalled();

        $giftCardConfiguration->setCode('test')->shouldBeCalled();
        $giftCardConfiguration->setEnabled(false)->shouldBeCalled();
        $giftCardConfiguration->setDefault(false)->shouldBeCalled();
        $giftCardConfiguration->setDefaultValidityPeriod(Argument::any())->shouldNotBeCalled();
        $giftCardConfiguration->setPageSize(Argument::any())->shouldNotBeCalled();
        $giftCardConfiguration->setOrientation(Argument::any())->shouldNotBeCalled();
        $giftCardConfiguration->setTemplate(Argument::any())->shouldNotBeCalled();

        $command = new CreateGiftCardConfiguration('test', false, false);
        $handler->__invoke($command);
    }
}
