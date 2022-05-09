<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class GenerateEncodedExamplePdfAction
{
    private GiftCardFactoryInterface $giftCardFactory;

    private GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private FormFactoryInterface $formFactory;

    public function __construct(
        GiftCardFactoryInterface $giftCardFactory,
        GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        FormFactoryInterface $formFactory
    ) {
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
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

        $pdfContent = $this->giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardConfiguration);

        return new Response(\base64_encode($pdfContent));
    }
}
