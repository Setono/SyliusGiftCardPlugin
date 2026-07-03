<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\ValidateAddToCartCommandClassPass;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ValidateAddToCartCommandClassPassTest extends TestCase
{
    private const PARAMETER = 'setono_sylius_gift_card.order.model.add_to_cart_command.class';

    /** @test */
    public function it_does_nothing_when_the_parameter_is_absent(): void
    {
        $this->expectNotToPerformAssertions();

        (new ValidateAddToCartCommandClassPass())->process(new ContainerBuilder());
    }

    /** @test */
    public function it_passes_when_the_configured_class_implements_the_interface(): void
    {
        $this->expectNotToPerformAssertions();

        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, AddToCartCommand::class);

        (new ValidateAddToCartCommandClassPass())->process($container);
    }

    /** @test */
    public function it_throws_when_the_configured_class_does_not_implement_the_interface(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);

        (new ValidateAddToCartCommandClassPass())->process($container);
    }
}
