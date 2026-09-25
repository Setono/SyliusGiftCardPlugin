<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * The designs a customer can pick from on the product page, and the setup warning built on them, come from these
 * queries, so they are run against the real database for every way a design can be (un)available in a channel
 */
final class GiftCardDesignRepositoryTest extends GiftCardFunctionalTestCase
{
    private GiftCardDesignRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GiftCardDesignRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card_design');
        $this->repository = $repository;
    }

    /**
     * The picker shows the designs in the order the merchant set, and designs sharing a position keep the order they
     * were created in, so the picker does not reshuffle between requests
     *
     * @test
     */
    public function it_finds_the_enabled_designs_of_a_channel_in_position_order(): void
    {
        $channel = $this->getChannel();

        $this->createDesign('third', 2, [$channel]);
        $this->createDesign('first', 0, [$channel]);
        $this->createDesign('second_a', 1, [$channel]);
        $this->createDesign('second_b', 1, [$channel]);
        $this->manager->clear();

        self::assertSame(
            ['first', 'second_a', 'second_b', 'third'],
            $this->codesOf($this->repository->findEnabledByChannel($this->getChannel())),
        );
    }

    /** @test */
    public function it_leaves_out_disabled_designs_and_designs_of_other_channels(): void
    {
        $channel = $this->getChannel();
        $otherChannel = $this->createChannel('OTHER_CHANNEL');

        $this->createDesign('here', 0, [$channel]);
        $this->createDesign('everywhere', 1, [$channel, $otherChannel]);
        $this->createDesign('disabled_here', 2, [$channel], enabled: false);
        $this->createDesign('only_there', 3, [$otherChannel]);
        $this->createDesign('nowhere', 4, []);
        $this->manager->clear();

        self::assertSame(['here', 'everywhere'], $this->codesOf($this->repository->findEnabledByChannel($this->getChannel())));
        self::assertSame(
            ['everywhere', 'only_there'],
            $this->codesOf($this->repository->findEnabledByChannel($this->channel('OTHER_CHANNEL'))),
        );
    }

    /** @test */
    public function it_finds_no_designs_for_a_channel_without_any(): void
    {
        $this->createDesign('elsewhere', 0, [$this->createChannel('OTHER_CHANNEL')]);

        self::assertSame([], $this->repository->findEnabledByChannel($this->getChannel()));
    }

    /**
     * Unlike the picker, the count includes disabled designs: it answers how many designs a channel has at all
     *
     * @test
     */
    public function it_counts_every_design_of_a_channel_whether_enabled_or_not(): void
    {
        $channel = $this->getChannel();
        $otherChannel = $this->createChannel('OTHER_CHANNEL');

        $this->createDesign('enabled', 0, [$channel]);
        $this->createDesign('disabled', 1, [$channel], enabled: false);
        $this->createDesign('shared', 2, [$channel, $otherChannel]);
        $this->createDesign('only_there', 3, [$otherChannel]);
        $emptyChannel = $this->createChannel('EMPTY_CHANNEL');

        self::assertSame(3, $this->repository->countByChannel($channel));
        self::assertSame(2, $this->repository->countByChannel($otherChannel));
        self::assertSame(0, $this->repository->countByChannel($emptyChannel));
    }

    /**
     * @param list<ChannelInterface> $channels
     */
    private function createDesign(string $code, int $position, array $channels, bool $enabled = true): void
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setName(ucfirst($code));
        $design->setPosition($position);
        $design->setEnabled($enabled);
        foreach ($channels as $channel) {
            $design->addChannel($channel);
        }

        $this->manager->persist($design);
        $this->manager->flush();
    }

    private function channel(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = self::getContainer()->get('sylius.repository.channel');

        $channel = $channelRepository->findOneByCode($code);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    /**
     * @param list<GiftCardDesignInterface> $designs
     *
     * @return list<string>
     */
    private function codesOf(array $designs): array
    {
        return array_map(static fn (GiftCardDesignInterface $design): string => (string) $design->getCode(), $designs);
    }
}
