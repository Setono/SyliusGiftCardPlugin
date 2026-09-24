<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignImageType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignTranslationType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class GiftCardDesignTypeTest extends TypeTestCase
{
    private const LOCALE = 'en_US';

    use ProphecyTrait;

    /** @test */
    public function it_maps_a_submitted_position(): void
    {
        $design = new GiftCardDesign();

        $form = $this->factory->create(GiftCardDesignType::class, $design);
        $form->submit($this->submission('7'));

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(7, $design->getPosition());
    }

    /**
     * The data mapper writes the submitted value into the setter during submit(), before validation runs, so a
     * blank position used to end the request in a 500. The setter accepts null and the NotNull constraint reports it
     *
     * @test
     */
    public function it_reports_a_blank_position_instead_of_crashing(): void
    {
        $design = new GiftCardDesign();

        $form = $this->factory->create(GiftCardDesignType::class, $design);
        $form->submit($this->submission(''));

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('position')->getErrors());
        self::assertNull($design->getPosition());
    }

    /** @test */
    public function it_maps_a_new_design_with_its_name_and_artwork(): void
    {
        $design = new GiftCardDesign();

        $form = $this->factory->create(GiftCardDesignType::class, $design);
        $form->submit($this->submission('0', images: [
            ['type' => GiftCardDesignImageInterface::TYPE_FRONT],
            ['type' => GiftCardDesignImageInterface::TYPE_BACK],
        ]));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        self::assertSame('classic', $design->getCode());
        self::assertTrue($design->isEnabled());

        $translation = $design->getTranslations()->get(self::LOCALE);
        self::assertInstanceOf(GiftCardDesignTranslation::class, $translation);
        self::assertSame('Classic', $translation->getName());

        // the images are added through the design, which is what makes the design their owner
        $front = $design->getFrontImage();
        $back = $design->getBackImage();
        self::assertNotNull($front);
        self::assertNotNull($back);
        self::assertSame($design, $front->getOwner());
        self::assertSame($design, $back->getOwner());
        self::assertCount(2, $design->getImages());
    }

    /** @test */
    public function it_removes_the_artwork_the_admin_deleted(): void
    {
        $front = $this->image(GiftCardDesignImageInterface::TYPE_FRONT);
        $back = $this->image(GiftCardDesignImageInterface::TYPE_BACK);

        $design = new GiftCardDesign();
        $design->addImage($front);
        $design->addImage($back);

        $form = $this->factory->create(GiftCardDesignType::class, $design);
        $form->submit($this->submission('0', images: [['type' => GiftCardDesignImageInterface::TYPE_FRONT]]));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        self::assertSame([$front], array_values($design->getImages()->toArray()));
        self::assertNull($back->getOwner(), 'an image without an owner is what Doctrine removes as an orphan');
    }

    /**
     * A design has one front and one back; a second image of either would never be shown. The images collection
     * bubbles its errors up, so the violation is reported on the form, whose errors the admin template renders
     *
     * @test
     */
    public function it_reports_a_repeated_image_type(): void
    {
        $form = $this->factory->create(GiftCardDesignType::class, new GiftCardDesign());
        $form->submit($this->submission('0', images: [
            ['type' => GiftCardDesignImageInterface::TYPE_FRONT],
            ['type' => GiftCardDesignImageInterface::TYPE_FRONT],
        ]));

        self::assertFalse($form->isValid());
        $errors = iterator_to_array($form->getErrors());
        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);
        self::assertSame('setono_sylius_gift_card.gift_card_design.images.unique_type', $errors[0]->getMessageTemplate());
    }

    /**
     * @param list<array<string, string>> $images
     *
     * @return array<string, mixed>
     */
    private function submission(string $position, array $images = []): array
    {
        return [
            'code' => 'classic',
            'translations' => [self::LOCALE => ['name' => 'Classic']],
            'position' => $position,
            'channels' => [],
            'enabled' => '1',
            'images' => $images,
        ];
    }

    private function image(string $type): GiftCardDesignImage
    {
        $image = new GiftCardDesignImage();
        $image->setType($type);

        return $image;
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $localeProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $localeProvider->getDefinedLocalesCodes()->willReturn([self::LOCALE]);
        $localeProvider->getDefaultLocaleCode()->willReturn(self::LOCALE);

        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository->findAll()->willReturn([]);

        $preloaded = new PreloadedExtension([
            new GiftCardDesignType(GiftCardDesign::class, ['setono_sylius_gift_card']),
            new GiftCardDesignTranslationType(GiftCardDesignTranslation::class, ['setono_sylius_gift_card']),
            new GiftCardDesignImageType(GiftCardDesignImage::class, ['setono_sylius_gift_card']),
            new ResourceTranslationsType($localeProvider->reveal()),
            new ChannelChoiceType($channelRepository->reveal()),
        ], []);

        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCardDesign.xml')
            ->getValidator()
        ;

        return [$preloaded, new ValidatorExtension($validator)];
    }
}
