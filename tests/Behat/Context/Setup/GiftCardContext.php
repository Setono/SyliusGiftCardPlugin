<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Common\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class GiftCardContext implements Context
{
    /** @var SharedStorageInterface */
    private $sharedStorage;

    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var GiftCardFactoryInterface */
    private $giftCardFactory;

    /** @var ObjectManager */
    private $productManager;

    public function __construct(
        SharedStorageInterface $sharedStorage,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardFactoryInterface $giftCardFactory,
        ObjectManager $productManager
    ) {
        $this->sharedStorage = $sharedStorage;
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardFactory = $giftCardFactory;
        $this->productManager = $productManager;
    }

    /**
     * todo this should probably be moved to a ProductContext instead
     *
     * @Given /^(this product) is a gift card$/
     */
    public function thisProductIsAGiftCard(ProductInterface $product): void
    {
        $product->setGiftCard(true);

        $this->productManager->flush();
    }

    /**
     * @Given /^the store has a gift card with code "([^"]+)" valued at ("[^"]+")$/
     */
    public function theStoreHasGiftCardWithCode(string $code, int $price): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        $giftCard = $this->giftCardFactory->createNew();
        $giftCard->setCode($code);
        $giftCard->setChannel($channel);
        $giftCard->setInitialAmount($price);
        $giftCard->setCurrencyCode($channel->getBaseCurrency()->getCode());

        $this->giftCardRepository->add($giftCard);
    }
}
