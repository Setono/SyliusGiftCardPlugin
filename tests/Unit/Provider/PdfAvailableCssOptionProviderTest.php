<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Provider\PdfAvailableCssOptionProvider;

final class PdfAvailableCssOptionProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_provides_available_options(): void
    {
        $pdfAvailableCssOptionProvider = new PdfAvailableCssOptionProvider();

        $expected = [
            '%logo_url%' => [
                'hint' => 'setono_sylius_gift_card.form.gift_card_configuration.css_option.logo_url_hint',
                'accessPath' => 'path',
            ],
        ];
        $result = $pdfAvailableCssOptionProvider->getAvailableOptions();

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function it_provides_options_value(): void
    {
        $pdfAvailableCssOptionProvider = new PdfAvailableCssOptionProvider();

        $expected = [
            '%logo_url%' => 'https://test.com',
        ];
        $context = [
            'path' => 'https://test.com',
        ];
        $result = $pdfAvailableCssOptionProvider->getOptionsValue($context);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function it_provides_options_hint(): void
    {
        $pdfAvailableCssOptionProvider = new PdfAvailableCssOptionProvider();

        $expected = [
            '%logo_url%' => 'setono_sylius_gift_card.form.gift_card_configuration.css_option.logo_url_hint',
        ];
        $result = $pdfAvailableCssOptionProvider->getOptionsHint();

        $this->assertEquals($expected, $result);
    }
}
