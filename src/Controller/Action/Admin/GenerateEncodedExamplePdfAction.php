<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Renderer\PDFRendererInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class GenerateEncodedExamplePdfAction
{
    private GiftCardFactoryInterface $giftCardFactory;

    private GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository;

    private PDFRendererInterface $giftCardPDFRenderer;

    private FormFactoryInterface $formFactory;

    public function __construct(
        GiftCardFactoryInterface $giftCardFactory,
        GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
        PDFRendererInterface $giftCardPDFRenderer,
        FormFactoryInterface $formFactory
    ) {
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardPDFRenderer = $giftCardPDFRenderer;
        $this->formFactory = $formFactory;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->giftCardFactory->createExample();

        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->find($id);
        Assert::isInstanceOf($giftCardConfiguration, GiftCardConfigurationInterface::class);

        $form = $this->formFactory->create(GiftCardConfigurationType::class, $giftCardConfiguration);
        $form->handleRequest($request);

        $response = $this->giftCardPDFRenderer->render($giftCard, $giftCardConfiguration);

        return new Response((string) $response->encode());
    }
}
