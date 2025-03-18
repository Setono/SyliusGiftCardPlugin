<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Api\Command\AddItemToCart as SetonoSyliusGiftCardAddItemToCart;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart as SyliusAddItemToCart;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Webmozart\Assert\Assert;

final class AddItemToCartHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductVariantRepositoryInterface $productVariantRepository,
        private readonly OrderModifierInterface $orderModifier,
        private readonly CartItemFactoryInterface $cartItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly GiftCardFactoryInterface $giftCardFactory,
        private readonly EntityManagerInterface $giftCardManager
    ) {
    }

    public function __invoke(SyliusAddItemToCart $addItemToCart): OrderInterface
    {
        /** @var ProductVariantInterface|null $productVariant */
        $productVariant = $this->productVariantRepository->findOneBy(['code' => $addItemToCart->productVariantCode]);

        Assert::notNull($productVariant);
        Assert::notNull($addItemToCart->orderTokenValue);

        /** @var OrderInterface|null $cart */
        $cart = $this->orderRepository->findCartByTokenValue($addItemToCart->orderTokenValue);

        Assert::notNull($cart);

        /** @var OrderItemInterface $cartItem */
        $cartItem = $this->cartItemFactory->createNew();
        $cartItem->setVariant($productVariant);

        if ($this->isGiftCard($addItemToCart, $productVariant)) {
            /** @var ProductInterface $product */
            $product = $productVariant->getProduct();
            if ($product->isGiftCardAmountConfigurable()) {
                /** @var SetonoSyliusGiftCardAddItemToCart $addItemToCart */
                $addItemToCart = $addItemToCart;
                /** @var int|null $giftCardAmount */
                $giftCardAmount = $addItemToCart->getAmount();
                Assert::notNull($giftCardAmount);
                $cartItem->setUnitPrice($giftCardAmount);
                $cartItem->setImmutable(true);
            }
        }

        $this->orderItemQuantityModifier->modify($cartItem, $addItemToCart->quantity);
        $this->orderModifier->addToOrder($cart, $cartItem);

        if ($this->isGiftCard($addItemToCart, $productVariant)) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($cartItem->getUnits() as $unit) {
                $giftCard = $this->giftCardFactory->createFromOrderItemUnitAndCart($unit, $cart);
                /** @var SetonoSyliusGiftCardAddItemToCart $addItemToCart */
                $addItemToCart = $addItemToCart;
                $giftCard->setCustomMessage($addItemToCart->getCustomMessage());

                // As the common flow for any add to cart action will flush later. Do not flush here.
                $this->giftCardManager->persist($giftCard);
            }
        }

        return $cart;
    }

    private function isGiftCard(SyliusAddItemToCart $addItemToCart, ProductVariantInterface $productVariant): bool
    {
        $product = $productVariant->getProduct();

        return $addItemToCart instanceof SetonoSyliusGiftCardAddItemToCart &&
            $product instanceof ProductInterface &&
            $product->isGiftCard();
    }
}
