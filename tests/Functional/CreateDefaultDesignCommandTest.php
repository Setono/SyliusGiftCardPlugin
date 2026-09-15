<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Command\CreateDefaultDesignCommand;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The default design used to be created on the fly while rendering the product page. It is now an explicit console
 * command, so it has to be safe to run on an installed shop as often as one likes.
 */
final class CreateDefaultDesignCommandTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_creates_the_default_design_in_every_channel(): void
    {
        $this->getChannel();
        $this->createChannel('SECOND_CHANNEL');

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Created the default gift card design "classic"', $tester->getDisplay());

        $design = $this->theOnlyDesign();
        self::assertSame(CreateDefaultDesignCommand::DEFAULT_DESIGN_CODE, $design->getCode());
        self::assertSame('Classic', $design->getName());
        self::assertTrue($design->isEnabled());
        self::assertEqualsCanonicalizing(['TEST_CHANNEL', 'SECOND_CHANNEL'], $this->channelCodesOf($design));

        $front = $design->getFrontImage();
        self::assertInstanceOf(GiftCardDesignImageInterface::class, $front);
        self::assertNotNull($front->getPath());
    }

    /** @test */
    public function it_is_idempotent(): void
    {
        $this->getChannel();

        $this->runCommand();
        $created = $this->theOnlyDesign();
        $this->manager->clear();

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already exists', $tester->getDisplay());
        self::assertStringContainsString('already available in every channel', $tester->getDisplay());

        $design = $this->theOnlyDesign();
        self::assertSame($created->getId(), $design->getId());
        self::assertCount(1, $design->getImages());
    }

    /** @test */
    public function it_adds_the_channels_an_existing_default_design_is_missing(): void
    {
        $this->getChannel();
        $this->runCommand();
        $this->manager->clear();

        $this->createChannel('SECOND_CHANNEL');
        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('SECOND_CHANNEL', $tester->getDisplay());

        $design = $this->theOnlyDesign();
        self::assertEqualsCanonicalizing(['TEST_CHANNEL', 'SECOND_CHANNEL'], $this->channelCodesOf($design));
        self::assertCount(1, $design->getImages());
    }

    private function runCommand(): CommandTester
    {
        /** @var KernelInterface $kernel */
        $kernel = self::getContainer()->get('kernel');

        $tester = new CommandTester((new Application($kernel))->find('setono:gift-card:create-default-design'));
        $tester->execute([]);

        return $tester;
    }

    private function theOnlyDesign(): GiftCardDesignInterface
    {
        /** @var GiftCardDesignRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card_design');

        $designs = $repository->findAll();
        self::assertCount(1, $designs);

        $design = reset($designs);
        self::assertInstanceOf(GiftCardDesignInterface::class, $design);

        return $design;
    }

    /**
     * @return list<string>
     */
    private function channelCodesOf(GiftCardDesignInterface $design): array
    {
        return array_values(array_map(
            static fn (ChannelInterface $channel): string => (string) $channel->getCode(),
            $design->getChannels()->toArray(),
        ));
    }
}
