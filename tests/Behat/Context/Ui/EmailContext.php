<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepository;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Test\Services\EmailCheckerInterface;
use Webmozart\Assert\Assert;

final class EmailContext implements Context
{
    /** @var EmailCheckerInterface */
    private $emailChecker;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var GiftCardRepository */
    private $giftCardRepository;

    public function __construct(
        EmailCheckerInterface $emailChecker,
        OrderRepositoryInterface $orderRepository,
        GiftCardRepository $giftCardRepository
    ) {
        $this->emailChecker = $emailChecker;
        $this->orderRepository = $orderRepository;
        $this->giftCardRepository = $giftCardRepository;
    }

    /**
     * @Then I should receive an email with gift card code
     */
    public function iShouldReceiveAnEmailWithGiftCard(): void
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findAll()[0];

        foreach ($order->getItems() as $orderItem) {
            /** @var ProductInterface $product */
            $product = $orderItem->getProduct();

            if (!$product->isGiftCard()) {
                continue;
            }

            foreach ($orderItem->getUnits() as $orderItemUnit) {
                $giftCard = $this->giftCardRepository->findOneByOrderItemUnit($orderItemUnit);

                Assert::true($giftCard->isEnabled(), 'Gift card is not enabled');

                $this->assertEmailContainsMessageTo($giftCard->getCode(), $order->getCustomer()->getEmail());
            }
        }
    }

    private function assertEmailContainsMessageTo(string $message, string $recipient): void
    {
        Assert::true($this->emailChecker->hasMessageTo($message, $recipient), 'The email is wrong');
    }
}
