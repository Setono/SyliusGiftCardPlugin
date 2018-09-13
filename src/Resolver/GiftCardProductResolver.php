<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class GiftCardProductResolver implements GiftCardProductResolverInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    public function __construct(GiftCardRepositoryInterface $giftCardRepository)
    {
        $this->giftCardRepository = $giftCardRepository;
    }

    public function isGiftCardProduct(ProductInterface $product): bool
    {
        return null !== $this->giftCardRepository->findOneByProduct($product);
    }
}
