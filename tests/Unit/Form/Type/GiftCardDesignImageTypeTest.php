<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignImageType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class GiftCardDesignImageTypeTest extends TypeTestCase
{
    /**
     * @test
     *
     * @dataProvider sides
     */
    public function it_maps_the_side_of_the_card_the_image_is_for(string $type): void
    {
        $image = new GiftCardDesignImage();

        $form = $this->factory->create(GiftCardDesignImageType::class, $image);
        $form->submit(['type' => $type]);

        self::assertTrue($form->isSynchronized());
        self::assertSame($type, $image->getType());
        self::assertNull($image->getFile(), 'an image is edited without uploading its file again');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sides(): iterable
    {
        yield 'the front' => [GiftCardDesignImageInterface::TYPE_FRONT];
        yield 'the back' => [GiftCardDesignImageInterface::TYPE_BACK];
    }

    /**
     * The PDF only ever draws a front and a back, so an image of any other type would be stored and never shown
     *
     * @test
     */
    public function it_rejects_a_type_that_is_not_a_side_of_the_card(): void
    {
        $image = new GiftCardDesignImage();

        $form = $this->factory->create(GiftCardDesignImageType::class, $image);
        $form->submit(['type' => 'inside']);

        self::assertFalse($form->get('type')->isSynchronized());
        self::assertSame(GiftCardDesignImageInterface::TYPE_FRONT, $image->getType(), 'the image keeps the type it had');
    }

    /**
     * The admin form theme renders the image row, with its preview, through the block named after the type's prefix
     *
     * @test
     */
    public function it_is_rendered_through_the_plugins_image_widget_block(): void
    {
        $vars = $this->factory->create(GiftCardDesignImageType::class, new GiftCardDesignImage())->createView()->vars;

        self::assertIsArray($vars);
        self::assertIsArray($vars['block_prefixes']);
        self::assertContains('setono_sylius_gift_card_gift_card_design_image', $vars['block_prefixes']);
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([
                new GiftCardDesignImageType(GiftCardDesignImage::class, ['setono_sylius_gift_card']),
            ], []),
        ];
    }
}
