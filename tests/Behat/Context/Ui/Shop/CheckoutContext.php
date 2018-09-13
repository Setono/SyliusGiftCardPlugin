<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardCodeRepositoryInterface;
use Sylius\Behat\Context\Setup\OrderContext;
use Sylius\Behat\Context\Ui\Shop\Checkout\CheckoutCompleteContext;
use Sylius\Behat\Context\Ui\Shop\Checkout\CheckoutThankYouContext;
use Sylius\Behat\Context\Ui\Shop\CheckoutContext as BaseCheckoutContext;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Webmozart\Assert\Assert;

final class CheckoutContext implements Context
{
    /** @var CheckoutCompleteContext */
    private $checkoutCompleteContext;

    /** @var OrderContext */
    private $orderContext;

    /** @var CheckoutThankYouContext */
    private $checkoutThankYouContext;

    /** @var BaseCheckoutContext */
    private $checkoutContext;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardCodeEntityManager;

    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    public function __construct(
        CheckoutCompleteContext $checkoutCompleteContext,
        OrderContext $orderContext,
        CheckoutThankYouContext $checkoutThankYouContext,
        BaseCheckoutContext $checkoutContext,
        OrderRepositoryInterface $orderRepository,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        EntityManagerInterface $giftCardCodeEntityManager,
        PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
        $this->checkoutCompleteContext = $checkoutCompleteContext;
        $this->orderContext = $orderContext;
        $this->checkoutThankYouContext = $checkoutThankYouContext;
        $this->checkoutContext = $checkoutContext;
        $this->orderRepository = $orderRepository;
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardCodeEntityManager = $giftCardCodeEntityManager;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @When I confirm my order and pay successfully
     */
    public function iConfirmMyOrderAndPaySuccessfully(): void
    {
        $this->checkoutCompleteContext->iConfirmMyOrder();

        $orders = $this->orderRepository->findAll();

        $this->orderContext->thisOrderIsAlreadyPaid($orders[0]);
    }

    /**
     * @When I place an order with a gift card code :code
     */
    public function iPlaceAnOrderWithAGiftCardCode(string $code): void
    {
        $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

        $order = $this->orderRepository->findAll()[0];

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->findAll()[0];

        $giftCardCode->setCurrentOrder($order);

        $this->giftCardCodeEntityManager->flush();

        $this->checkoutContext->iProceedSelectingPaymentMethod($paymentMethod->getName());
        $this->checkoutCompleteContext->iConfirmMyOrder();
        $this->checkoutThankYouContext->iShouldSeeTheThankYouPage();
    }

    /**
     * @Then The gift card with the code :code should be inactive
     */
    public function theGiftCardWithTheCodeShouldBeInactive(string $code): void
    {
        $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

        $this->giftCardCodeEntityManager->refresh($giftCardCode);

        Assert::same($giftCardCode->getAmount(), 0);
        Assert::false($giftCardCode->isActive());
    }

    /**
     * @When I place an order with a gift card code :code which covers the entire total order
     */
    public function iPlaceAnOrderWithAGiftCardCodeWhichCoversTheEntireTotalOrder(string $code): void
    {
        $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

        $order = $this->orderRepository->findAll()[0];

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->findAll()[0];

        $giftCardCode->setCurrentOrder($order);

        $this->giftCardCodeEntityManager->flush();

        $this->checkoutContext->iProceedSelectingShippingCountryAndShippingMethod();
        $this->checkoutCompleteContext->iConfirmMyOrder();
        $this->checkoutThankYouContext->iShouldSeeTheThankYouPage();
    }

    /**
     * @Then /^The gift card with the code "([^"]+)" should be active and have value ("[^"]+")$/
     */
    public function theGiftCardWithTheCodeShouldBeActiveAndHaveValue(string $code, int $amount): void
    {
        $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

        $this->giftCardCodeEntityManager->refresh($giftCardCode);

        Assert::same($giftCardCode->getAmount(), $amount);
        Assert::true($giftCardCode->isActive());
    }
}
