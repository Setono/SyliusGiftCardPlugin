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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ResendGiftCardEmailAction
{
    private GiftCardEmailManagerInterface $giftCardEmailManager;

    private GiftCardRepositoryInterface $giftCardRepository;

    private UrlGeneratorInterface $router;

    public function __construct(
        GiftCardEmailManagerInterface $giftCardEmailManager,
        GiftCardRepositoryInterface $giftCardRepository,
        UrlGeneratorInterface $router
    ) {
        $this->giftCardEmailManager = $giftCardEmailManager;
        $this->giftCardRepository = $giftCardRepository;
        $this->router = $router;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            $this->addFlash($request, 'error', [
                'message' => 'setono_sylius_gift_card.gift_card.not_found',
                'parameters' => ['%id%' => $id],
            ]);

            return new RedirectResponse($this->getRedirectUrl($request));
        }

        $order = $giftCard->getOrder();
        $customer = $giftCard->getCustomer();

        if ($order instanceof OrderInterface) {
            $this->giftCardEmailManager->sendEmailWithGiftCardsFromOrder($order, [$giftCard]);
            $this->addFlash($request, 'success', [
                'message' => 'setono_sylius_gift_card.gift_card.resent',
                'parameters' => ['%id%' => $id],
            ]);
        } elseif ($customer instanceof CustomerInterface) {
            $this->giftCardEmailManager->sendEmailToCustomerWithGiftCard($customer, $giftCard);
            $this->addFlash($request, 'success', [
                'message' => 'setono_sylius_gift_card.gift_card.resent',
                'parameters' => ['%id%' => $id],
            ]);
        } else {
            $this->addFlash($request, 'error', [
                'message' => 'setono_sylius_gift_card.gift_card.impossible_to_resend_email',
                'parameters' => ['%id%' => $id],
            ]);
        }

        return new RedirectResponse($this->getRedirectUrl($request));
    }

    private function getRedirectUrl(Request $request): string
    {
        if ($request->headers->has('referer')) {
            /** @var mixed $filtered */
            $filtered = filter_var($request->headers->get('referer'), FILTER_SANITIZE_URL);

            if (is_string($filtered)) {
                return $filtered;
            }
        }

        return $this->router->generate('setono_sylius_gift_card_admin_gift_card_index');
    }

    /**
     * @param mixed $message
     */
    private function addFlash(Request $request, string $type, $message): void
    {
        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
