<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Provider\DefaultPdfCssProviderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Webmozart\Assert\Assert;

final class LoadDefaultPdfCssAction
{
    private GiftCardFactoryInterface $giftCardFactory;

    private RepositoryInterface $giftCardConfigurationRepository;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private DefaultPdfCssProviderInterface $defaultPdfCssProvider;

    public function __construct(
        GiftCardFactoryInterface $giftCardFactory,
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        DefaultPdfCssProviderInterface $defaultPdfCssProvider
    ) {
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->defaultPdfCssProvider = $defaultPdfCssProvider;
    }

    public function __invoke(int $id): JsonResponse
    {
        $giftCard = $this->giftCardFactory->createExample();
        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->find($id);
        Assert::isInstanceOf($giftCardConfiguration, GiftCardConfigurationInterface::class);

        $defaultCss = $this->defaultPdfCssProvider->getDefaultCss();
        $giftCardConfiguration->setPdfRenderingCss($defaultCss);

        $pdfContent = $this->giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardConfiguration);

        return new JsonResponse([
            'css' => $defaultCss,
            'pdfContent' => \base64_encode($pdfContent),
        ]);
    }
}
