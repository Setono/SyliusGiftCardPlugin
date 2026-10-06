<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignTranslationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

/**
 * The name of a design in one locale, which the shop's design picker labels the design with
 */
final class GiftCardDesignTranslationTypeTest extends TypeTestCase
{
    /** @test */
    public function it_maps_the_name(): void
    {
        $translation = new GiftCardDesignTranslation();

        $form = $this->submit($translation, 'Happy birthday');

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('Happy birthday', $translation->getName());
    }

    /** @test */
    public function it_reports_a_blank_name_in_the_plugins_words(): void
    {
        $error = $this->nameError($this->submit(new GiftCardDesignTranslation(), ''));

        self::assertSame('setono_sylius_gift_card.gift_card_design.name.not_blank', $error->getMessageTemplate());
    }

    /**
     * The column holds 255 characters, so a longer name would get past the form and end the request in a 500
     *
     * @test
     */
    public function it_reports_a_name_longer_than_the_column(): void
    {
        $form = $this->submit(new GiftCardDesignTranslation(), str_repeat('a', 255));
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $violation = $this->nameError($this->submit(new GiftCardDesignTranslation(), str_repeat('a', 256)))->getCause();

        self::assertInstanceOf(ConstraintViolation::class, $violation);
        self::assertSame(Length::TOO_LONG_ERROR, $violation->getCode());
    }

    /**
     * @return FormInterface<GiftCardDesignTranslation>
     */
    private function submit(GiftCardDesignTranslation $translation, string $name): FormInterface
    {
        /** @var FormInterface<GiftCardDesignTranslation> $form */
        $form = $this->factory->create(GiftCardDesignTranslationType::class, $translation);
        $form->submit(['name' => $name]);

        return $form;
    }

    /**
     * @param FormInterface<GiftCardDesignTranslation> $form
     */
    private function nameError(FormInterface $form): FormError
    {
        self::assertFalse($form->isValid());

        $errors = iterator_to_array($form->get('name')->getErrors());
        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);

        return $errors[0];
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        // The rules live in the validation mapping rather than on the form, so the mapping is what is loaded here
        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCardDesignTranslation.xml')
            ->getValidator()
        ;

        return [
            new PreloadedExtension([
                new GiftCardDesignTranslationType(GiftCardDesignTranslation::class, ['setono_sylius_gift_card']),
            ], []),
            new ValidatorExtension($validator),
        ];
    }
}
