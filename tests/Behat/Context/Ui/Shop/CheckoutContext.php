<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Behat\Context\Setup\OrderContext;
use Sylius\Behat\Context\Ui\Shop\Checkout\CheckoutCompleteContext;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Webmozart\Assert\Assert;

final class CheckoutContext implements Context
{
    /** @var CheckoutCompleteContext */
    private $checkoutCompleteContext;

    /** @var OrderContext */
    private $orderContext;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var EntityManagerInterface */
    private $giftCardManager;

    public function __construct(
        CheckoutCompleteContext $checkoutCompleteContext,
        OrderContext $orderContext,
        OrderRepositoryInterface $orderRepository,
        EntityManagerInterface $giftCardManager
    ) {
        $this->checkoutCompleteContext = $checkoutCompleteContext;
        $this->orderContext = $orderContext;
        $this->orderRepository = $orderRepository;
        $this->giftCardManager = $giftCardManager;
    }

    /**
     * @When I confirm my order and pay successfully
     */
    public function iConfirmMyOrderAndPaySuccessfully(): void
    {
        $this->checkoutCompleteContext->iConfirmMyOrder();

        /** @var OrderInterface[] $orders */
        $orders = $this->orderRepository->findAll();

        $this->orderContext->thisOrderIsAlreadyPaid($orders[0]);
    }

    /**
     * @Then the gift card :giftCard should be disabled
     */
    public function theGiftCardWithTheCodeShouldBeInactive(GiftCardInterface $giftCard): void
    {
        // todo this is needed, but I don't know why
        $this->giftCardManager->refresh($giftCard);

        Assert::same($giftCard->getAmount(), 0);
        Assert::false($giftCard->isEnabled());
    }
}
