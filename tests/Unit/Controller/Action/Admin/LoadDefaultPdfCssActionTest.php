<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Controller\Action\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\LoadDefaultPdfCssAction;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\DefaultPdfCssProviderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class LoadDefaultPdfCssActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_loads_default_css_and_generates_pdf(): void
    {
        $id = 8;
        $giftCard = new GiftCard();
        $exampleGiftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $exampleGiftCardFactory->createExample()->willReturn($giftCard);
        $giftCardConfiguration = new GiftCardConfiguration();
        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardConfigurationRepository->find($id)->willReturn($giftCardConfiguration);
        $defaultCssProvider = $this->prophesize(DefaultPdfCssProviderInterface::class);
        $defaultCssProvider->getDefaultCss()->willReturn('body {background-color: red;}');

        $giftCardPdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardConfiguration)->shouldBeCalled();

        $action = new LoadDefaultPdfCssAction(
            $exampleGiftCardFactory->reveal(),
            $giftCardConfigurationRepository->reveal(),
            $giftCardPdfGenerator->reveal(),
            $defaultCssProvider->reveal()
        );
        $jsonResponse = $action($id);
        $response = \json_decode($jsonResponse->getContent(), true);
        $this->assertEquals('body {background-color: red;}', $response['css']);
    }
}
