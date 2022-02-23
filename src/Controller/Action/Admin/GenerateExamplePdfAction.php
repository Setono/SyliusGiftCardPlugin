<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\ExampleGiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class GenerateExamplePdfAction
{
    private ExampleGiftCardFactoryInterface $exampleGiftCardFactory;

    private RepositoryInterface $giftCardConfigurationRepository;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private FormFactoryInterface $formFactory;

    public function __construct(
        ExampleGiftCardFactoryInterface $exampleGiftCardFactory,
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        FormFactoryInterface $formFactory
    ) {
        $this->exampleGiftCardFactory = $exampleGiftCardFactory;
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->formFactory = $formFactory;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->exampleGiftCardFactory->createNew();
        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->find($id);
        Assert::isInstanceOf($giftCardConfiguration, GiftCardConfigurationInterface::class);

        $form = $this->formFactory->create(GiftCardConfigurationType::class, $giftCardConfiguration);
        $form->handleRequest($request);

        $this->giftCardPdfGenerator->generateAndSavePdf($giftCard, $giftCardConfiguration);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
