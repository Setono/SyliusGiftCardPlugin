<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Controller\Action\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\GenerateEncodedExamplePdfAction;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

final class GenerateEncodedExamplePdfActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_generates_encoded_pdf(): void
    {
        $id = 8;
        $giftCard = new GiftCard();
        $exampleGiftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $exampleGiftCardFactory->createExample()->willReturn($giftCard);
        $giftCardConfiguration = new GiftCardConfiguration();
        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardConfigurationRepository->find($id)->willReturn($giftCardConfiguration);

        $request = new Request();
        $form = $this->prophesize(FormInterface::class);
        $formFactory = $this->prophesize(FormFactoryInterface::class);
        $formFactory->create(GiftCardConfigurationType::class, $giftCardConfiguration)->willReturn($form);
        $form->handleRequest($request)->shouldBeCalled();

        $giftCardPdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardConfiguration)->shouldBeCalled();

        $action = new GenerateEncodedExamplePdfAction(
            $exampleGiftCardFactory->reveal(),
            $giftCardConfigurationRepository->reveal(),
            $giftCardPdfGenerator->reveal(),
            $formFactory->reveal()
        );
        $response = $action($request, $id);
        $this->assertEquals(null, $response->getContent());
    }
}
