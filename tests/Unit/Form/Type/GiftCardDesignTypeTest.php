<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignImageType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignTranslationType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
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

    /**
     * @return array<string, mixed>
     */
    private function submission(string $position): array
    {
        return [
            'code' => 'classic',
            'translations' => [self::LOCALE => ['name' => 'Classic']],
            'position' => $position,
            'channels' => [],
            'enabled' => '1',
            'images' => [],
        ];
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
