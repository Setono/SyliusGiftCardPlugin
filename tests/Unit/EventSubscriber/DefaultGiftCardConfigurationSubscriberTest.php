<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventSubscriber\DefaultGiftCardConfigurationSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;

final class DefaultGiftCardConfigurationSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_sets_all_existing_to_not_default_when_creating_new(): void
    {
        $existing1 = $this->getExistingGiftCardConfiguration(1);
        $existing2 = $this->getExistingGiftCardConfiguration(2);

        $repository = $this->prophesize(GiftCardConfigurationRepositoryInterface::class);
        $repository->findAll()->willReturn([$existing1, $existing2]);

        $giftCardConfiguration = $this->getExistingGiftCardConfiguration();

        $event = new ResourceControllerEvent($giftCardConfiguration);

        $subscriber = new DefaultGiftCardConfigurationSubscriber($repository->reveal());
        $subscriber->handle($event);

        self::assertFalse($existing1->isDefault());
        self::assertFalse($existing2->isDefault());
        self::assertTrue($giftCardConfiguration->isDefault());
    }

    /**
     * @test
     */
    public function it_sets_all_existing_to_not_default_when_updating_existing(): void
    {
        $existing1 = $this->getExistingGiftCardConfiguration(1);
        $existing2 = $this->getExistingGiftCardConfiguration(2);

        $repository = $this->prophesize(GiftCardConfigurationRepositoryInterface::class);
        $repository->findAll()->willReturn([$existing1, $existing2]);

        $giftCardConfiguration = $this->getExistingGiftCardConfiguration(2);

        $event = new ResourceControllerEvent($giftCardConfiguration);

        $subscriber = new DefaultGiftCardConfigurationSubscriber($repository->reveal());
        $subscriber->handle($event);

        self::assertFalse($existing1->isDefault());
        self::assertTrue($existing2->isDefault());
        self::assertTrue($giftCardConfiguration->isDefault());
    }

    private function getExistingGiftCardConfiguration(int $id = null, bool $default = true): GiftCardConfigurationInterface
    {
        $obj = new class($id) extends GiftCardConfiguration {
            public function __construct(int $id = null)
            {
                parent::__construct();

                $this->id = $id;
            }

            public function getId(): ?int
            {
                return $this->id;
            }
        };

        $obj->setDefault($default);

        return $obj;
    }
}
