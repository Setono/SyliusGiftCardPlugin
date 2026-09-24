<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

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
