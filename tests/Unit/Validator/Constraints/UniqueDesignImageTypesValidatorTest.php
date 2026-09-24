<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\UniqueDesignImageTypes;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\UniqueDesignImageTypesValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<UniqueDesignImageTypesValidator>
 */
final class UniqueDesignImageTypesValidatorTest extends ConstraintValidatorTestCase
{
    /** @test */
    public function it_does_not_add_a_violation_for_one_image_per_type(): void
    {
        $design = new GiftCardDesign();
        $design->addImage($this->image(GiftCardDesignImageInterface::TYPE_FRONT));
        $design->addImage($this->image(GiftCardDesignImageInterface::TYPE_BACK));

        $this->validator->validate($design, new UniqueDesignImageTypes());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_adds_a_violation_when_a_type_is_used_more_than_once(): void
    {
        $design = new GiftCardDesign();
        $design->addImage($this->image(GiftCardDesignImageInterface::TYPE_FRONT));
        $design->addImage($this->image(GiftCardDesignImageInterface::TYPE_FRONT));

        $this->validator->validate($design, new UniqueDesignImageTypes());

        $this->buildViolation('setono_sylius_gift_card.gift_card_design.images.unique_type')
            ->setParameter('{{ type }}', GiftCardDesignImageInterface::TYPE_FRONT)
            ->atPath('property.path.images')
            ->assertRaised();
    }

    /**
     * The violation names the type, once however often it is repeated, so the admin reads what to fix rather than
     * one error per surplus image
     *
     * @test
     */
    public function it_adds_one_violation_per_repeated_type(): void
    {
        $design = new GiftCardDesign();
        foreach ([GiftCardDesignImageInterface::TYPE_FRONT, GiftCardDesignImageInterface::TYPE_BACK] as $type) {
            for ($i = 0; $i < 3; ++$i) {
                $design->addImage($this->image($type));
            }
        }

        $this->validator->validate($design, new UniqueDesignImageTypes());

        $this->buildViolation('setono_sylius_gift_card.gift_card_design.images.unique_type')
            ->setParameter('{{ type }}', GiftCardDesignImageInterface::TYPE_FRONT)
            ->atPath('property.path.images')
            ->buildNextViolation('setono_sylius_gift_card.gift_card_design.images.unique_type')
            ->setParameter('{{ type }}', GiftCardDesignImageInterface::TYPE_BACK)
            ->atPath('property.path.images')
            ->assertRaised();
    }

    /** @test */
    public function it_does_not_add_a_violation_for_a_design_without_images(): void
    {
        $this->validator->validate(new GiftCardDesign(), new UniqueDesignImageTypes());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_ignores_anything_but_a_design(): void
    {
        $this->validator->validate(new \stdClass(), new UniqueDesignImageTypes());
        $this->validator->validate(null, new UniqueDesignImageTypes());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new GiftCardDesign(), new NotBlank());
    }

    protected function createValidator(): UniqueDesignImageTypesValidator
    {
        return new UniqueDesignImageTypesValidator();
    }

    private function image(string $type): GiftCardDesignImage
    {
        $image = new GiftCardDesignImage();
        $image->setType($type);

        return $image;
    }
}
