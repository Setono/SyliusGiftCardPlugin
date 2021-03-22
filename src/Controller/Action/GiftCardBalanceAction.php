<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardBalanceCollection;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The purpose of this class is to show the gift card balance, i.e. what amount is still available on enabled gift cards
 */
final class GiftCardBalanceAction
{
    private GiftCardRepositoryInterface $giftCardRepository;

    private Environment $twig;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        Environment $twig
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->twig = $twig;
    }

    public function __invoke(Request $request): Response
    {
        $giftCardBalanceCollection = GiftCardBalanceCollection::createFromGiftCards(
            $this->giftCardRepository->findEnabled()
        );

        return (new Response())->setContent($this->twig->render('@SetonoSyliusGiftCardPlugin/Admin/giftCardBalance.html.twig', ['giftCardBalanceCollection' => $giftCardBalanceCollection]));
    }
}
