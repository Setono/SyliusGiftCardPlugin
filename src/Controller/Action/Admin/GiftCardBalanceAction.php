<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class GiftCardBalanceAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $balances = $this->giftCardRepository->findBalance();

        return new Response($this->twig->render('@SetonoSyliusGiftCardPlugin/admin/gift_card/balance.html.twig', [
            'balances' => $balances,
        ]));
    }
}
