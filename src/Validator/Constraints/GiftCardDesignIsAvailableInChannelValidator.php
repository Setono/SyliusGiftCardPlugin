<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A card is issued with one of the designs its channel offers, the same designs the channel's customers pick from.
 * The admin's create form offers the designs of every channel, since the channel is chosen on that form too, so this
 * is where a design of another channel is turned down.
 *
 * Only a card being issued is checked. A card that exists keeps the design it was issued with, even once that design
 * is disabled or taken out of the channel: the card still prints with it, and the card is validated whenever it is
 * edited, so holding it to the channel's current designs would make it impossible to edit. The edit form only offers
 * the channel's designs and the one the card has
 */
final class GiftCardDesignIsAvailableInChannelValidator extends ConstraintValidator
{
    public function __construct(private readonly GiftCardDesignProviderInterface $designProvider)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardDesignIsAvailableInChannel) {
            throw new UnexpectedTypeException($constraint, GiftCardDesignIsAvailableInChannel::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof GiftCardInterface) {
            throw new UnexpectedTypeException($value, GiftCardInterface::class);
        }

        if (null !== $value->getId()) {
            return;
        }

        // A card without a design prints the default layout, and without a channel there is nothing to hold it to
        $design = $value->getDesign();
        $channel = $value->getChannel();
        if (null === $design || null === $channel) {
            return;
        }

        foreach ($this->designProvider->getDesigns($channel) as $offered) {
            if ($offered === $design || (null !== $design->getCode() && $offered->getCode() === $design->getCode())) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ design }}', (string) ($design->getName() ?? $design->getCode()))
            ->setParameter('{{ channel }}', (string) ($channel->getName() ?? $channel->getCode()))
            ->atPath('design')
            ->addViolation();
    }
}
