<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders an example gift card with the given design so an admin can preview how the PDF will look.
 *
 * The preview is rendered for one of the design's own channels: the one named by the optional `channel`
 * query parameter, otherwise the first one. A design that is not assigned to any channel yet falls back to
 * the first channel of the shop. The shop channel context is deliberately not used here: it resolves the
 * channel from the request hostname, which the admin hostname need not match, and on a multi-channel shop
 * there is no single channel left to fall back to.
 */
final class PreviewGiftCardDesignPdfAction
{
    /** Arbitrary, only ever rendered into a throwaway preview PDF */
    private const PREVIEW_AMOUNT = 5000;

    /**
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly GiftCardDesignRepositoryInterface $designRepository,
        private readonly GiftCardFactoryInterface $giftCardFactory,
        private readonly GiftCardPdfGeneratorInterface $pdfGenerator,
        private readonly RepositoryInterface $channelRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $design = $this->designRepository->find($id);
        if (!$design instanceof GiftCardDesignInterface) {
            throw new NotFoundHttpException();
        }

        $channelCode = $request->query->getString('channel');
        $channel = $this->resolveChannel($design, '' === $channelCode ? null : $channelCode);

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

    /**
     * @throws NotFoundHttpException when the requested channel is not one of the design's, or when there is no channel at all
     */
    private function resolveChannel(GiftCardDesignInterface $design, ?string $channelCode): ChannelInterface
    {
        if (null !== $channelCode) {
            foreach ($design->getChannels() as $channel) {
                if ($channel instanceof ChannelInterface && $channel->getCode() === $channelCode) {
                    return $channel;
                }
            }

            // An explicit request for a channel the design is not sold in is a bad URL, not something to
            // quietly answer with a different channel: that would show the wrong currency, which is exactly
            // what previewing through the shop channel context did
            throw new NotFoundHttpException(sprintf(
                'The gift card design "%s" is not available in the channel "%s"',
                (string) $design->getCode(),
                $channelCode,
            ));
        }

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
