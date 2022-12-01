<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Renderer\GiftCardPDFRendererInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadGiftCardPdfAction
{
    private GiftCardConfigurationProviderInterface $configurationProvider;

    private GiftCardPDFRendererInterface $giftCardPDFRenderer;

    public function __construct(
        GiftCardConfigurationProviderInterface $configurationProvider,
        GiftCardPDFRendererInterface $giftCardPDFRenderer
    ) {
        $this->configurationProvider = $configurationProvider;
        $this->giftCardPDFRenderer = $giftCardPDFRenderer;
    }

    public function __invoke(GiftCardInterface $data): Response
    {
        $configuration = $this->configurationProvider->getConfigurationForGiftCard($data);
        if (!$configuration instanceof GiftCardConfigurationInterface) {
            throw new NotFoundHttpException('No configuration found for this GiftCard');
        }

        return $this->giftCardPDFRenderer->render($data, $configuration)->getHttpResponse();
    }
}
