<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\ExampleGiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Provider\DefaultPdfCssProviderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class LoadDefaultPdfCssAction
{
    private ExampleGiftCardFactoryInterface $exampleGiftCardFactory;

    private RepositoryInterface $giftCardConfigurationRepository;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private DefaultPdfCssProviderInterface $defaultPdfCssProvider;

    public function __construct(
        ExampleGiftCardFactoryInterface $exampleGiftCardFactory,
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        DefaultPdfCssProviderInterface $defaultPdfCssProvider
    ) {
        $this->exampleGiftCardFactory = $exampleGiftCardFactory;
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->defaultPdfCssProvider = $defaultPdfCssProvider;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->exampleGiftCardFactory->createNew();
        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->find($id);
        Assert::isInstanceOf($giftCardConfiguration, GiftCardConfigurationInterface::class);

        $defaultCss = $this->defaultPdfCssProvider->getDefaultCss();
        $giftCardConfiguration->setPdfRenderingCss($defaultCss);

        $this->giftCardPdfGenerator->generateAndSavePdf($giftCard, $giftCardConfiguration);

        return new Response($defaultCss);
    }
}
