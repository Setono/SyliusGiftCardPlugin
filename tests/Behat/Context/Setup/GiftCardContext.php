<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardCodeFactoryInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class GiftCardContext implements Context
{
    /** @var SharedStorageInterface */
    private $sharedStorage;

    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var GiftCardFactoryInterface */
    private $giftCardFactory;

    /** @var EntityManagerInterface */
    private $giftCardEntityManager;

    /** @var GiftCardCodeFactoryInterface */
    private $giftCardCodeFactory;

    /** @var EntityManagerInterface */
    private $giftCardCodeEntityManager;

    public function __construct(
        SharedStorageInterface $sharedStorage,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardFactoryInterface $giftCardFactory,
        EntityManagerInterface $giftCardEntityManager,
        GiftCardCodeFactoryInterface $giftCardCodeFactory,
        EntityManagerInterface $giftCardCodeEntityManager
    ) {
        $this->sharedStorage = $sharedStorage;
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardEntityManager = $giftCardEntityManager;
        $this->giftCardCodeFactory = $giftCardCodeFactory;
        $this->giftCardCodeEntityManager = $giftCardCodeEntityManager;
    }

    /**
     * @Given /^(this product) is a gift card$/
     */
    public function thisProductIsAGiftCard(ProductInterface $product): void
    {
        $giftCard = $this->giftCardFactory->createWithProduct($product);

        $this->giftCardRepository->add($giftCard);
    }

    /**
     * @Given the store has gift card :product with code :code
     */
    public function theStoreHasGiftCardWithCode(ProductInterface $product, string $code): void
    {
        /** @var ProductVariantInterface $productVariant */
        $productVariant = $product->getVariants()->first();

        /** @var ChannelPricingInterface $channelPricing */
        $channelPricing = $productVariant->getChannelPricings()->first();

        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        $giftCard = $this->giftCardRepository->findOneByProduct($product);

        /** @var GiftCardCodeInterface $giftCardCode */
        $giftCardCode = $this->giftCardCodeFactory->createNew();

        $giftCardCode->setActive(true);
        $giftCardCode->setAmount($channelPricing->getPrice());
        $giftCardCode->setCurrencyCode(
            $channel->getBaseCurrency()->getCode()
        );
        $giftCardCode->setCode($code);
        $giftCardCode->setGiftCard($giftCard);
        $giftCardCode->setChannel($channel);

        $this->giftCardCodeEntityManager->persist($giftCardCode);
        $this->giftCardCodeEntityManager->flush();
    }
}
