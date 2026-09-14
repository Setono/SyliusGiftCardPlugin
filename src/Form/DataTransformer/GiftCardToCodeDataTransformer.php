<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Webmozart\Assert\Assert;

/**
 * @implements DataTransformerInterface<GiftCardInterface, string>
 */
final class GiftCardToCodeDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardCodeNormalizerInterface $codeNormalizer,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param GiftCardInterface|mixed $value
     */
    public function transform($value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        Assert::isInstanceOf($value, GiftCardInterface::class);

        return $value->getCode();
    }

    public function reverseTransform($value): ?GiftCardInterface
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new TransformationFailedException('Expected the value to be a string');
        }

        $channel = $this->channelContext->getChannel();

        // Customers type the code as it is printed on the card, grouped by dashes and in whatever case they
        // like, while the stored code is the canonical form
        $giftCard = $this->giftCardRepository->findOneEnabledByCodeAndChannel(
            $this->codeNormalizer->normalize($value),
            $channel,
        );

        if (null !== $giftCard) {
            return $giftCard;
        }

        // The customer only gets the form's generic invalid_message, so that a code that exists cannot be
        // told apart from one that does not. The attempt is logged instead, both to help the shop owner
        // answer "why does my gift card not work" and to make a run of guesses visible
        $this->logger->info(sprintf(
            'No enabled gift card with the code "%s" exists in the channel "%s"',
            self::sanitizeForLog($value),
            (string) $channel->getCode(),
        ));

        throw new TransformationFailedException('setono_sylius_gift_card.ui.gift_card_code_does_not_exist');
    }

    /**
     * Cuts a submitted code down to something safe to write to a log: bounded in length and without the
     * control characters a guesser could otherwise use to forge log lines of their own
     */
    private static function sanitizeForLog(string $code): string
    {
        return (string) preg_replace('/[^[:print:]]/', '', mb_substr($code, 0, 64));
    }
}
