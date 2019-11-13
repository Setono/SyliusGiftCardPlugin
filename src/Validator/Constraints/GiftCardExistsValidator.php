<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class GiftCardExistsValidator extends ConstraintValidator
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(GiftCardRepositoryInterface $giftCardRepository, ChannelContextInterface $channelContext)
    {
        $this->giftCardRepository = $giftCardRepository;
        $this->channelContext = $channelContext;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardExists) {
            throw new UnexpectedTypeException($constraint, GiftCardExists::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $giftCard = $this->giftCardRepository->findOneEnabledByCodeAndChannel($value, $this->channelContext->getChannel());

        if ($giftCard === null) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation()
            ;
        }
    }
}
