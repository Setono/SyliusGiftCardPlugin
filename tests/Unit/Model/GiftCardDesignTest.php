<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation;
use Sylius\Component\Core\Model\Channel;

final class GiftCardDesignTest extends TestCase
{
    /** @test */
    public function it_is_named_in_the_current_locale_and_falls_back_to_the_fallback_locale(): void
    {
        $design = $this->design();
        $design->setName('Classic');

        self::assertInstanceOf(GiftCardDesignTranslation::class, $design->getTranslation('en_US'));

        $danish = new GiftCardDesignTranslation();
        $danish->setLocale('da_DK');
        $danish->setName('Klassisk');
        $design->addTranslation($danish);

        $design->setCurrentLocale('da_DK');
        self::assertSame('Klassisk', $design->getName());

        $design->setCurrentLocale('fr_FR');
        self::assertSame('Classic', $design->getName(), 'a locale without a name falls back to the fallback locale');
    }

    /**
     * The design picker and the admin grid print the design; one nobody has named yet is shown by its code
     *
     * @test
     */
    public function it_is_represented_by_its_name_or_else_its_code(): void
    {
        $design = $this->design();
        $design->setCode('classic');

        self::assertSame('classic', (string) $design);

        $design->setName('Classic');
        self::assertSame('Classic', (string) $design);
    }

    /** @test */
    public function it_finds_the_artwork_for_each_side_of_the_card(): void
    {
        $design = $this->design();
        self::assertFalse($design->hasImages());
        self::assertNull($design->getFrontImage());
        self::assertNull($design->getBackImage());

        $back = $this->image(GiftCardDesignImageInterface::TYPE_BACK);
        $design->addImage($back);

        self::assertTrue($design->hasImages());
        self::assertNull($design->getFrontImage(), 'a back image is not the front');
        self::assertSame($back, $design->getBackImage());

        $front = $this->image(GiftCardDesignImageInterface::TYPE_FRONT);
        $design->addImage($front);

        self::assertSame($front, $design->getFrontImage());
        self::assertSame([$front], array_values($design->getImagesByType(GiftCardDesignImageInterface::TYPE_FRONT)->toArray()));
    }

    /**
     * An image belongs to the design it is added to, and one removed from it belongs to nothing, which is what
     * makes Doctrine remove it as an orphan
     *
     * @test
     */
    public function it_owns_the_images_it_holds(): void
    {
        $image = $this->image(GiftCardDesignImageInterface::TYPE_FRONT);
        $design = $this->design();

        $design->addImage($image);

        self::assertTrue($design->hasImage($image));
        self::assertSame($design, $image->getOwner());

        $design->removeImage($image);

        self::assertFalse($design->hasImage($image));
        self::assertNull($image->getOwner());
    }

    /** @test */
    public function it_leaves_an_image_of_another_design_alone(): void
    {
        $image = $this->image(GiftCardDesignImageInterface::TYPE_FRONT);
        $owner = $this->design();
        $owner->addImage($image);

        $this->design()->removeImage($image);

        self::assertSame($owner, $image->getOwner());
    }

    /** @test */
    public function it_is_offered_in_each_channel_once(): void
    {
        $channel = new Channel();
        $design = $this->design();

        $design->addChannel($channel);
        $design->addChannel($channel);

        self::assertTrue($design->hasChannel($channel));
        self::assertCount(1, $design->getChannels());

        $design->removeChannel($channel);

        self::assertFalse($design->hasChannel($channel));
        self::assertCount(0, $design->getChannels());
    }

    /**
     * The admin form adds an image before the admin picks its side, and a card always has a front
     *
     * @test
     */
    public function it_starts_an_image_out_as_the_front(): void
    {
        self::assertSame(GiftCardDesignImageInterface::TYPE_FRONT, (new GiftCardDesignImage())->getType());
    }

    private function design(): GiftCardDesign
    {
        $design = new GiftCardDesign();
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');

        return $design;
    }

    private function image(string $type): GiftCardDesignImage
    {
        $image = new GiftCardDesignImage();
        $image->setType($type);

        return $image;
    }
}
