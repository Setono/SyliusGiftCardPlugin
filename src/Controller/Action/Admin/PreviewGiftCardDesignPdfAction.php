<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * Renders an example gift card with the given design so an admin can preview how the PDF will look
 */
final class PreviewGiftCardDesignPdfAction
{
    /** Arbitrary, only ever rendered into a throwaway preview PDF */
    private const PREVIEW_AMOUNT = 5000;

    public function __construct(
        private readonly GiftCardDesignRepositoryInterface $designRepository,
        private readonly GiftCardFactoryInterface $giftCardFactory,
        private readonly GiftCardPdfGeneratorInterface $pdfGenerator,
        private readonly ChannelContextInterface $channelContext,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(int $id): Response
    {
        $design = $this->designRepository->find($id);
        if (!$design instanceof GiftCardDesignInterface) {
            throw new NotFoundHttpException();
        }

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $giftCard = $this->giftCardFactory->createForChannel($channel);
        $giftCard->setDesign($design);
        $giftCard->setAmount(self::PREVIEW_AMOUNT);
        $giftCard->setInitialAmount(self::PREVIEW_AMOUNT);
        $giftCard->setCustomMessage($this->translator->trans('setono_sylius_gift_card.ui.preview_message'));

        return new Response($this->pdfGenerator->generate($giftCard), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-design-preview.pdf"',
        ]);
    }
}
