<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class GiftCardDesignRequiredValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardDesignProviderInterface $designProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardDesignRequired) {
            throw new UnexpectedTypeException($constraint, GiftCardDesignRequired::class);
        }

        if (null !== $value) {
            return;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return;
        }

        if (!$channel instanceof ChannelInterface || [] === $this->designProvider->getDesigns($channel)) {
            return;
        }

        $this->context->addViolation($constraint->message);
    }
}
