<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The setono_gift_card_design fixture gives the demo shop and the end to end suite the designs customers pick from,
 * so it is loaded here the way sylius:fixtures:load loads it, and what lands in the database is checked
 */
final class GiftCardDesignFixtureTest extends GiftCardFunctionalTestCase
{
    use LoadsFixturesTrait;

    private const BUNDLED_IMAGE = '@SetonoSyliusGiftCardPlugin/Resources/fixtures/default_background.png';

    private GiftCardDesignRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GiftCardDesignRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card_design');
        $this->repository = $repository;

        $this->getChannel();
        $this->createChannel('OTHER_CHANNEL');
    }

    /** @test */
    public function it_loads_a_design_with_the_given_options(): void
    {
        $this->loadFixture('setono_gift_card_design', ['custom' => [[
            'code' => 'winter',
            'name' => 'Winter',
            'position' => 3,
            'enabled' => false,
            'channels' => ['TEST_CHANNEL'],
            'back_image' => self::BUNDLED_IMAGE,
        ]]]);

        $design = $this->findDesign('winter');
        self::assertSame('Winter', $design->getName());
        self::assertSame(3, $design->getPosition());
        self::assertFalse($design->isEnabled());
        self::assertSame(['TEST_CHANNEL'], $this->channelCodesOf($design));

        $front = $design->getFrontImage();
        $back = $design->getBackImage();
        self::assertInstanceOf(GiftCardDesignImageInterface::class, $front);
        self::assertInstanceOf(GiftCardDesignImageInterface::class, $back);
        self::assertNotNull($front->getPath(), 'the front image should have been uploaded');
        self::assertNotNull($back->getPath(), 'the back image should have been uploaded');
        self::assertNotSame($front->getPath(), $back->getPath(), 'each side is uploaded as a file of its own');
    }

    /**
     * A design needs nothing but a front to be usable, so everything else has a default: the bundled artwork on the
     * front, no back, enabled, first in line and available in every channel. Naming no channels means every channel
     *
     * @test
     */
    public function it_defaults_to_an_enabled_design_with_the_bundled_front_in_every_channel(): void
    {
        $this->loadFixture('setono_gift_card_design', [
            'random' => 2,
            'custom' => [['code' => 'no_channels', 'channels' => []]],
        ]);

        $designs = $this->repository->findAll();
        self::assertCount(3, $designs);

        $codes = [];
        foreach ($designs as $design) {
            self::assertInstanceOf(GiftCardDesignInterface::class, $design);
            $codes[] = (string) $design->getCode();

            self::assertNotSame('', (string) $design->getName());
            self::assertSame(0, $design->getPosition());
            self::assertTrue($design->isEnabled());
            self::assertEqualsCanonicalizing(['TEST_CHANNEL', 'OTHER_CHANNEL'], $this->channelCodesOf($design));
            self::assertNotNull($design->getFrontImage()?->getPath());
            self::assertNull($design->getBackImage());
        }

        self::assertCount(3, array_unique($codes), 'every generated design gets a code of its own');
    }

    /** @test */
    public function it_rejects_an_image_that_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->loadFixture('setono_gift_card_design', ['custom' => [[
            'code' => 'missing',
            'front_image' => '@SetonoSyliusGiftCardPlugin/Resources/fixtures/does_not_exist.png',
        ]]]);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidDesignOptions
     *
     * @param array<string, mixed> $options
     */
    public function it_rejects_invalid_options(array $options): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadFixture('setono_gift_card_design', ['custom' => [$options]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidDesignOptions(): iterable
    {
        yield 'an empty name' => [['name' => '']];
        yield 'a position that is not an integer' => [['position' => 'first']];
        yield 'an empty front image' => [['front_image' => '']];
        yield 'channels that are not a list' => [['channels' => 'TEST_CHANNEL']];
        yield 'an unknown option' => [['colour' => 'gold']];
    }

    private function findDesign(string $code): GiftCardDesignInterface
    {
        $this->manager->clear();

        $design = $this->repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(GiftCardDesignInterface::class, $design, sprintf('the fixture should have created design %s', $code));

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
