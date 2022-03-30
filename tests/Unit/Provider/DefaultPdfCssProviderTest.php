<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Provider\DefaultPdfCssProvider;
use Twig\Environment;

final class DefaultPdfCssProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_provides_default_css_from_template(): void
    {
        $css = 'body{font-size: 2rem;}';
        $cssTemplateFile = '@Super/template.css.twig';
        $twig = $this->prophesize(Environment::class);
        $twig->render($cssTemplateFile)->willReturn($css);

        $defaultPdfCssProvider = new DefaultPdfCssProvider($cssTemplateFile, $twig->reveal());

        $this->assertEquals($css, $defaultPdfCssProvider->getDefaultCss());
    }
}
