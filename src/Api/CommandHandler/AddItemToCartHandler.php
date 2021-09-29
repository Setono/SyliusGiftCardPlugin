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
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Webmozart\Assert\Assert;

final class AddItemToCartHandler implements MessageHandlerInterface
{
    private OrderRepositoryInterface $orderRepository;

    private ProductVariantRepositoryInterface $productVariantRepository;

    private OrderModifierInterface $orderModifier;

    private CartItemFactoryInterface $cartItemFactory;

    private OrderItemQuantityModifierInterface $orderItemQuantityModifier;

    private GiftCardFactoryInterface $giftCardFactory;

    private EntityManagerInterface $giftCardManager;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ProductVariantRepositoryInterface $productVariantRepository,
        OrderModifierInterface $orderModifier,
        CartItemFactoryInterface $cartItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        GiftCardFactoryInterface $giftCardFactory,
        EntityManagerInterface $giftCardManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->orderModifier = $orderModifier;
        $this->cartItemFactory = $cartItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardManager = $giftCardManager;
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

        if ($addItemToCart instanceof SetonoSyliusGiftCardAddItemToCart) {
            /** @var ProductInterface $product */
            $product = $productVariant->getProduct();
            if ($product->isGiftCardAmountConfigurable()) {
                $giftCardAmount = $addItemToCart->getAmount();
                Assert::notNull($giftCardAmount);
                $cartItem->setUnitPrice($giftCardAmount);
                $cartItem->setImmutable(true);
            }
        }

        $this->orderItemQuantityModifier->modify($cartItem, $addItemToCart->quantity);
        $this->orderModifier->addToOrder($cart, $cartItem);

        if ($addItemToCart instanceof SetonoSyliusGiftCardAddItemToCart) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($cartItem->getUnits() as $unit) {
                $giftCard = $this->giftCardFactory->createFromOrderItemUnitAndCart($unit, $cart);
                $giftCard->setCustomMessage($addItemToCart->getCustomMessage());

                // As the common flow for any add to cart action will flush later. Do not flush here.
                $this->giftCardManager->persist($giftCard);
            }
        }

        return $cart;
    }
}
