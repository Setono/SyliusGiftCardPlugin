<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Transform;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardContext implements Context
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    public function __construct(GiftCardRepositoryInterface $giftCardRepository)
    {
        $this->giftCardRepository = $giftCardRepository;
    }

    /**
     * @Transform :giftCard
     */
    public function getGiftCardByCode(string $code): GiftCardInterface
    {
        return $this->giftCardRepository->findOneByCode($code);
    }
}
