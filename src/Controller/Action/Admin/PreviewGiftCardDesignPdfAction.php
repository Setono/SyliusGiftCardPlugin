<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders an example gift card with the given design so an admin can preview how the PDF will look.
 *
 * The preview is rendered for the first of the design's own channels. A design that is not assigned to any
 * channel yet falls back to the first channel of the shop. The shop channel context is deliberately not used
 * here: it resolves the channel from the request hostname, which the admin hostname need not match, and on a
 * multi-channel shop there is no single channel left to fall back to.
 */
final class PreviewGiftCardDesignPdfAction
{
    /** Arbitrary, only ever rendered into a throwaway preview PDF */
    private const PREVIEW_AMOUNT = 5000;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly GiftCardDesignRepositoryInterface $designRepository,
        private readonly GiftCardFactoryInterface $giftCardFactory,
        private readonly GiftCardPdfGeneratorInterface $pdfGenerator,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(int $id): Response
    {
        $design = $this->designRepository->find($id);
        if (!$design instanceof GiftCardDesignInterface) {
            throw new NotFoundHttpException();
        }

        $giftCard = $this->giftCardFactory->createForChannel($this->resolveChannel($design));
        $giftCard->setDesign($design);
        $giftCard->setAmount(self::PREVIEW_AMOUNT);
        $giftCard->setInitialAmount(self::PREVIEW_AMOUNT);
        $giftCard->setCustomMessage($this->translator->trans('setono_sylius_gift_card.ui.preview_message'));

        return new Response($this->pdfGenerator->generate($giftCard), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-design-preview.pdf"',
        ]);
    }

    /**
     * @throws NotFoundHttpException when there is no channel at all
     */
    private function resolveChannel(GiftCardDesignInterface $design): ChannelInterface
    {
        $channel = $design->getChannels()->first();
        if (false === $channel) {
            $channel = $this->channelRepository->findOneBy([]);
        }

        if (!$channel instanceof ChannelInterface) {
            throw new NotFoundHttpException('There is no channel to render the gift card design preview for');
        }

        return $channel;
    }
}
