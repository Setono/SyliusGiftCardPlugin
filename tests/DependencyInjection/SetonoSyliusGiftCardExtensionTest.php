<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;

final class SetonoSyliusGiftCardExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [new SetonoSyliusGiftCardExtension()];
    }

    /**
     * @test
     */
    public function after_loading_the_correct_parameter_has_been_set(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_gift_card.driver', SyliusResourceBundle::DRIVER_DOCTRINE_ORM);
    }
}
