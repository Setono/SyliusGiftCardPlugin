<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueDesignImageTypesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueDesignImageTypes) {
            throw new UnexpectedTypeException($constraint, UniqueDesignImageTypes::class);
        }

        if (!$value instanceof GiftCardDesignInterface) {
            return;
        }

        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($value->getImages() as $image) {
            $type = (string) $image->getType();
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        foreach ($counts as $type => $count) {
            if ($count > 1) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ type }}', $type)
                    ->atPath('images')
                    ->addViolation();
            }
        }
    }
}
