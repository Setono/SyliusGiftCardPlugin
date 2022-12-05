<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventSubscriber\DefaultGiftCardConfigurationSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;

final class DefaultGiftCardConfigurationSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_sets_all_existing_to_non_default(): void
    {
        $existing1 = new GiftCardConfiguration();
        $existing1->setDefault(true);

        $existing2 = new GiftCardConfiguration();
        $existing2->setDefault(true);

        $repository = $this->prophesize(GiftCardConfigurationRepositoryInterface::class);
        $repository->findAll()->willReturn([$existing1, $existing2]);

        $giftCardConfiguration = new GiftCardConfiguration();
        $giftCardConfiguration->setDefault(true);

        $event = new ResourceControllerEvent($giftCardConfiguration);

        $subscriber = new DefaultGiftCardConfigurationSubscriber($repository->reveal());
        $subscriber->handle($event);

        self::assertFalse($existing1->isDefault());
        self::assertFalse($existing2->isDefault());
        self::assertTrue($giftCardConfiguration->isDefault());
    }
}
