<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Security\GiftCardVoter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class DownloadGiftCardPdfAction
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var AuthorizationCheckerInterface */
    private $authChecker;

    /** @var FlashBagInterface */
    private $flashBag;

    public function __invoke(Request $request, int $giftCardId): Response
    {
        $giftCard = $this->giftCardRepository->find($giftCardId);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException('Gift card not found');
        }
        if (!$this->authChecker->isGranted(GiftCardVoter::READ, $giftCard)) {
            $this->flashBag->add('error', 'setono_sylius_gift_card.gift_card.read_error');

            return new RedirectResponse(filter_var($request->headers->get('referer'), \FILTER_SANITIZE_URL));
        }

        $configuration = $giftCard->getConfiguration();
    }
}
