<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class GiftCardSetupChecker implements GiftCardSetupCheckerInterface
{
    use ORMTrait;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param class-string $productClass
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly GiftCardDesignRepositoryInterface $designRepository,
        ManagerRegistry $managerRegistry,
        private readonly string $productClass,
        private readonly GiftCardPaymentMethodProviderInterface $paymentMethodProvider,
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getChannelsWithoutDesign(): array
    {
        $selling = $this->getCodesOfChannelsSellingGiftCards();
        if ([] === $selling) {
            return [];
        }

        $channels = [];

        foreach ($this->channelRepository->findAll() as $channel) {
            if (!$channel instanceof ChannelInterface || !$channel->isEnabled()) {
                continue;
            }

            if (!in_array($channel->getCode(), $selling, true)) {
                continue;
            }

            if ([] === $this->designRepository->findEnabledByChannel($channel)) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    public function isPaymentMethodMissing(): bool
    {
        return null === $this->paymentMethodProvider->findPaymentMethod() && $this->hasGiftCardsAtStake();
    }

    public function getDisabledPaymentMethod(): ?PaymentMethodInterface
    {
        $paymentMethod = $this->paymentMethodProvider->findPaymentMethod();
        if (null === $paymentMethod || $paymentMethod->isEnabled()) {
            return null;
        }

        return $this->hasGiftCardsAtStake() ? $paymentMethod : null;
    }

    /**
     * Whether some enabled channel sells gift cards, or customers hold gift cards they could spend: a shop that issues
     * its cards in the admin sells none, and neither does a merchant who took the gift card product offline along with
     * the payment method, while their customers' cards stop working all the same
     */
    private function hasGiftCardsAtStake(): bool
    {
        return $this->isSellingGiftCards() || [] !== $this->giftCardRepository->findBalance();
    }

    /**
     * Whether some enabled channel sells gift cards
     */
    private function isSellingGiftCards(): bool
    {
        $selling = $this->getCodesOfChannelsSellingGiftCards();
        if ([] === $selling) {
            return false;
        }

        foreach ($this->channelRepository->findBy(['code' => $selling]) as $channel) {
            if ($channel instanceof ChannelInterface && $channel->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The gift card flag lives on the host's product class, so the products are queried by class name rather
     * than through a repository method the plugin cannot add to Sylius' product repository. The codes are
     * deduplicated here rather than with DISTINCT: the association carries an order by the channel id, which
     * MySQL 8 refuses to combine with DISTINCT on another column
     *
     * @return list<string>
     */
    private function getCodesOfChannelsSellingGiftCards(): array
    {
        /** @var list<string> $codes */
        $codes = $this->getManager($this->productClass)
            ->createQuery(sprintf(
                'SELECT channel.code FROM %s product JOIN product.channels channel WHERE product.giftCard = true AND product.enabled = true',
                $this->productClass,
            ))
            ->getSingleColumnResult()
        ;

        return array_values(array_unique($codes));
    }
}
