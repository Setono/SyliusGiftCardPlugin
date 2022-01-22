<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminGeneratePdfAction
{
    private GiftCardFactoryInterface $giftCardFactory;

    private RepositoryInterface $giftCardConfigurationRepository;

    private GiftCardPdfGeneratorInterface $pdfGenerator;

    private FormFactoryInterface $formFactory;

    public function __construct(
        GiftCardFactoryInterface $giftCardFactory,
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardPdfGeneratorInterface $pdfGenerator,
        FormFactoryInterface $formFactory
    ) {
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->pdfGenerator = $pdfGenerator;
        $this->formFactory = $formFactory;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->giftCardFactory->createDummy();
        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->find($id);

        $form = $this->formFactory->create(GiftCardConfigurationType::class, $giftCardConfiguration);
        $form->handleRequest($request);

        $this->pdfGenerator->generateAndSavePdf($giftCard, $giftCardConfiguration);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
