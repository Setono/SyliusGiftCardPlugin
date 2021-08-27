<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadGiftCardPdfAction
{
    private GiftCardChannelConfigurationProviderInterface $configurationProvider;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    public function __construct(
        GiftCardChannelConfigurationProviderInterface $configurationProvider,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator
    ) {
        $this->configurationProvider = $configurationProvider;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
    }

    public function __invoke(GiftCardInterface $data): Response
    {
        $giftCard = $data;

        $configuration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);
        if (!$configuration instanceof GiftCardConfigurationInterface) {
            throw new NotFoundHttpException('No configuration found for this GiftCard');
        }

        return $this->giftCardPdfGenerator->generatePdfResponse($giftCard, $configuration);
    }
}
