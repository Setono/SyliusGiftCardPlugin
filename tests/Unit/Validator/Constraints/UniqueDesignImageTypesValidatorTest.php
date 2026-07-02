<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\UniqueDesignImageTypes;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\UniqueDesignImageTypesValidator;
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
