<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\ValidateAddToCartCommandClassPass;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
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

    /**
     * What the README tells an application with its own add to cart command to do
     *
     * @test
     */
    public function it_passes_when_the_configured_class_extends_the_plugins_command(): void
    {
        $this->expectNotToPerformAssertions();

        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, ApplicationAddToCartCommand::class);

        (new ValidateAddToCartCommandClassPass())->process($container);
    }

    /**
     * The message is the point of the pass: it names the parameter, the offending class and the way out, so the
     * application developer does not have to find the pass to understand the failure
     *
     * @test
     */
    public function it_throws_an_actionable_message_when_the_configured_class_does_not_implement_the_interface(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, \stdClass::class);

        try {
            (new ValidateAddToCartCommandClassPass())->process($container);
            self::fail('The pass should reject a class that does not implement the interface');
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            self::assertStringContainsString(sprintf('"%s"', self::PARAMETER), $message);
            self::assertStringContainsString(sprintf('"%s" does not', \stdClass::class), $message);
            self::assertStringContainsString(sprintf('must implement "%s"', AddToCartCommandInterface::class), $message);
            self::assertStringContainsString(sprintf('extend "%s"', AddToCartCommand::class), $message);
        }
    }

    /** @test */
    public function it_throws_when_the_configured_class_does_not_exist(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, 'App\Order\MisspelledAddToCartCommand');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('but "App\Order\MisspelledAddToCartCommand" does not');

        (new ValidateAddToCartCommandClassPass())->process($container);
    }

    /** @test */
    public function it_names_the_type_of_a_parameter_that_is_not_a_class_name(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('but "null" does not');

        (new ValidateAddToCartCommandClassPass())->process($container);
    }
}

class ApplicationAddToCartCommand extends AddToCartCommand
{
}
