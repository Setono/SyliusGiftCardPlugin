<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardSetupCheckerInterface;
use Setono\SyliusGiftCardPlugin\Twig\Extension\GiftCardSetupExtension;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardSetupRuntime;
use Sylius\Component\Core\Model\Channel;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * The admin layout and the gift card indexes ask for the channels without a design by this function name, so it is
 * exercised the way those templates call it
 */
final class GiftCardSetupExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_gives_templates_the_channels_that_sell_gift_cards_without_a_design(): void
    {
        $web = new Channel();
        $web->setName('Web store');
        $mobile = new Channel();
        $mobile->setName('Mobile store');

        $checker = $this->prophesize(GiftCardSetupCheckerInterface::class);
        $checker->getChannelsWithoutDesign()->willReturn([$web, $mobile]);

        $twig = new Environment(new ArrayLoader([
            'warning' => '{{ setono_gift_card_channels_without_design()|map(channel => channel.name)|join(", ") }}',
        ]));
        $twig->addExtension(new GiftCardSetupExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            GiftCardSetupRuntime::class => static fn (): GiftCardSetupRuntime => new GiftCardSetupRuntime($checker->reveal()),
        ]));

        self::assertSame('Web store, Mobile store', $twig->render('warning'));
    }
}
