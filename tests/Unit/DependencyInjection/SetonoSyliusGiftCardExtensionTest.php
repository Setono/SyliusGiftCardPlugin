<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
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
        $this->assertContainerBuilderHasParameter('setono_sylius_gift_card.code_length', 20);
        $this->assertContainerBuilderHasParameter('setono_sylius_gift_card.pdf_rendering.default_orientation', 'Portrait');
        $this->assertContainerBuilderHasParameter('setono_sylius_gift_card.pdf_rendering.available_orientations', ['Portrait', 'Landscape']);
        $this->assertContainerBuilderHasParameter('setono_sylius_gift_card.pdf_rendering.default_page_size', 'A4');
        $this->assertContainerBuilderHasParameter('setono_sylius_gift_card.pdf_rendering.available_page_sizes', [
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A0,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A1,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A2,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A3,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A4,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A5,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A6,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A7,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A8,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_A9,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B0,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B1,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B2,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B3,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B4,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B5,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B6,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B7,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B8,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B9,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_B10,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_C5E,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_COMM_10_E,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_DLE,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_EXECUTIVE,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_FOLIO,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_LEDGER,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_LEGAL,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_LETTER,
            PdfRenderingOptionsProviderInterface::PAGE_SIZE_TABLOID,
        ]);
    }
}
