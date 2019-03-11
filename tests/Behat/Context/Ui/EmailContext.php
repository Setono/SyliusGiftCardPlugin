<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepository;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardProductResolverInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Test\Services\EmailCheckerInterface;
use Webmozart\Assert\Assert;

final class EmailContext implements Context
{
    /** @var SharedStorageInterface */
    private $sharedStorage;

    /** @var EmailCheckerInterface */
    private $emailChecker;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var GiftCardCodeRepository */
    private $giftCardCodeRepository;

    /** @var GiftCardProductResolverInterface */
    private $giftCardProductResolver;

    public function __construct(
        SharedStorageInterface $sharedStorage,
        EmailCheckerInterface $emailChecker,
        OrderRepositoryInterface $orderRepository,
        GiftCardCodeRepository $giftCardCodeRepository,
        GiftCardProductResolverInterface $giftCardProductResolver
    ) {
        $this->sharedStorage = $sharedStorage;
        $this->emailChecker = $emailChecker;
        $this->orderRepository = $orderRepository;
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardProductResolver = $giftCardProductResolver;
    }

    /**
     * @Then I should be notified that email with gift card code
     */
    public function iShouldBeNotifiedThatEmailWithGiftCardCode(): void
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findAll()[0];

        foreach ($order->getItems() as $orderItem) {
            if (true === $this->giftCardProductResolver->isGiftCardProduct($orderItem->getProduct())) {
                /** @var GiftCardCodeInterface $giftCardCode */
                $giftCardCode = $this->giftCardCodeRepository->findOneBy(['orderItem' => $orderItem]);

                Assert::true($giftCardCode->isActive());

                $this->assertEmailContainsMessageTo($giftCardCode->getCode(), $order->getCustomer()->getEmail());
            }
        }
    }

    private function assertEmailContainsMessageTo(string $message, string $recipient): void
    {
        Assert::true($this->emailChecker->hasMessageTo($message, $recipient));
    }
}
