<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardBalanceCollection;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardBalanceAction
{
    private GiftCardRepositoryInterface $giftCardRepository;

    public function __construct(GiftCardRepositoryInterface $giftCardRepository)
    {
        $this->giftCardRepository = $giftCardRepository;
    }

    public function __invoke(): GiftCardBalanceCollection
    {
        return GiftCardBalanceCollection::createFromGiftCards(
            $this->giftCardRepository->findEnabled()
        );
    }
}
