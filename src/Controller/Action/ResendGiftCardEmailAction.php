<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use const FILTER_SANITIZE_URL;
use function filter_var;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ResendGiftCardEmailAction
{
    /** @var GiftCardEmailManagerInterface */
    private $giftCardEmailManager;

    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        GiftCardEmailManagerInterface $giftCardEmailManager,
        GiftCardRepositoryInterface $giftCardRepository,
        FlashBagInterface $flashBag,
        UrlGeneratorInterface $router
    ) {
        $this->giftCardEmailManager = $giftCardEmailManager;
        $this->giftCardRepository = $giftCardRepository;
        $this->flashBag = $flashBag;
        $this->router = $router;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            $this->flashBag->add('error', [
                'message' => 'setono_sylius_gift_card.gift_card.not_found',
                'parameters' => ['%id%' => $id],
            ]);

            return new RedirectResponse($this->getRedirectRoute($request));
        }

        if ($giftCard->getOrder() instanceof OrderInterface) {
            $this->giftCardEmailManager->sendEmailWithGiftCardsFromOrder($giftCard->getOrder(), [$giftCard]);
            $this->flashBag->add('success', [
                'message' => 'setono_sylius_gift_card.gift_card.resent',
                'parameters' => ['%id%' => $id],
            ]);
        } elseif ($giftCard->getCustomer() instanceof CustomerInterface) {
            $this->giftCardEmailManager->sendEmailToCustomerWithGiftCard($giftCard->getCustomer(), $giftCard);
            $this->flashBag->add('success', [
                'message' => 'setono_sylius_gift_card.gift_card.resent',
                'parameters' => ['%id%' => $id],
            ]);
        } else {
            $this->flashBag->add('error', [
                'message' => 'setono_sylius_gift_card.gift_card.impossible_to_resend_email',
                'parameters' => ['%id%' => $id],
            ]);
        }

        return new RedirectResponse($this->getRedirectRoute($request));
    }

    private function getRedirectRoute(Request $request): string
    {
        if ($request->headers->has('referer')) {
            $filtered = filter_var($request->headers->get('referer'), FILTER_SANITIZE_URL);

            if (false === $filtered) {
                return $this->router->generate('gift_card_admin_gift_card_index');
            }
        }

        return $this->router->generate('gift_card_admin_gift_card_index');
    }
}
