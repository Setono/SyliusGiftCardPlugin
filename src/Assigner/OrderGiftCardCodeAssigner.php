<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Assigner;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardCodeFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardCodeAssigner implements OrderGiftCardCodeAssignerInterface
{
    /** @var GiftCardCodeFactoryInterface */
    private $giftCardCodeFactory;

    /** @var GiftCardCodeGeneratorInterface */
    private $giftCardCodeGenerator;

    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var GiftCardOrderEmailManagerInterface */
    private $giftCardOrderEmailManager;

    /** @var EntityManagerInterface */
    private $giftCardEntityManager;

    public function __construct(
        GiftCardCodeFactoryInterface $giftCardCodeFactory,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardOrderEmailManagerInterface $giftCardOrderEmailManager,
        EntityManagerInterface $giftCardEntityManager
    ) {
        $this->giftCardCodeFactory = $giftCardCodeFactory;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardOrderEmailManager = $giftCardOrderEmailManager;
        $this->giftCardEntityManager = $giftCardEntityManager;
    }

    public function assignGiftCardCode(OrderInterface $order): void
    {
        $giftCardCodes = [];

        if (null === $order->getChannel()) {
            return;
        }

        /** @var CurrencyInterface|null $currency */
        $currency = $order->getChannel()->getBaseCurrency();
        Assert::isInstanceOf($currency, CurrencyInterface::class);

        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();

            if (null === $product) {
                continue;
            }

            $giftCard = $this->giftCardRepository->findOneByProduct($product);

            if (null === $giftCard) {
                continue;
            }

            for ($i = 0; $i < $orderItem->getQuantity(); ++$i) {
                $giftCardCode = $this->giftCardCodeFactory->createForGiftCardAndOrderItem($giftCard, $orderItem);

                $giftCardCode->setInitialAmount($orderItem->getUnitPrice());
                $giftCardCode->setAmount($orderItem->getUnitPrice());
                $giftCardCode->setCurrencyCode($currency->getCode());
                $giftCardCode->setChannel($order->getChannel());
                $giftCardCode->setCode($this->giftCardCodeGenerator->generate());
                $giftCardCode->setActive(true);

                $this->giftCardEntityManager->persist($giftCardCode);
                $this->giftCardEntityManager->flush();

                $giftCardCodes[] = $giftCardCode;
            }
        }

        if (\count($giftCardCodes) > 0) {
            $this->giftCardOrderEmailManager->sendEmailWithGiftCardCodes($order, $giftCardCodes);
        }
    }
}
