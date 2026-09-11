<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\ValidateAddToCartCommandClassPass;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

final class ValidateAddToCartCommandClassPassTest extends TestCase
{
    private const PARAMETER = 'setono_sylius_gift_card.order.model.add_to_cart_command.class';

    /**
     * The plugin always declares the parameter, and an application that unset it would fail the container
     * build anyway on the two services interpolating it, so the pass does not tolerate it being absent
     *
     * @test
     */
    public function it_throws_when_the_parameter_is_absent(): void
    {
        $this->expectException(ParameterNotFoundException::class);

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
