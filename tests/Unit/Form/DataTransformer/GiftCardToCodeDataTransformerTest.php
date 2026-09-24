<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\DataTransformer;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusGiftCardPlugin\Form\DataTransformer\GiftCardToCodeDataTransformer;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * The PDF prints the code grouped by dashes and customers type what they see, so the cart form has to look
 * the card up by the canonical code rather than by the keystrokes
 */
final class GiftCardToCodeDataTransformerTest extends TestCase
{
    use ProphecyTrait;

    private const CODE = 'ABCDEFGHJKMNPQRS';

    /**
     * @test
     *
     * @dataProvider renderingsOfTheCode
     */
    public function it_resolves_the_same_gift_card_however_the_code_is_written(string $input): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class)->reveal();

        $transformer = $this->transformer([self::CODE => $giftCard]);

        self::assertSame($giftCard, $transformer->reverseTransform($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function renderingsOfTheCode(): iterable
    {
        yield 'as printed on the card, in lower case' => ['abcd-efgh-jkmn-pqrs'];
        yield 'grouped by spaces, with surrounding whitespace' => [' ABCD EFGH JKMN PQRS '];
        yield 'the bare code in lower case' => ['abcdefghjkmnpqrs'];
    }

    /** @test */
    public function it_fails_when_no_gift_card_has_the_code(): void
    {
        $transformer = $this->transformer(['NOSUCHCARD000000' => null]);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('nosu-chca-rd00-0000');
    }

    /**
     * The code a customer typed may belong to a disabled card or to one from another channel, which is still
     * spendable there, so the log says which code it was without spelling it out
     *
     * @test
     */
    public function it_logs_a_code_it_cannot_resolve_masked(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $transformer = $this->transformer(['NOSUCHCARD000000' => null], $logger->reveal());

        try {
            $transformer->reverseTransform('nosu-chca-rd00-0000');
            self::fail('The code should not have resolved');
        } catch (TransformationFailedException) {
        }

        $logger->info(Argument::that(static fn (string $message): bool => str_contains($message, '************0000') && !str_contains($message, 'NOSUCHCARD')))
            ->shouldHaveBeenCalledOnce();
    }

    /**
     * The repository only answers to the canonical codes listed here, so a lookup with anything else, the raw
     * input for instance, is an unexpected call and fails the test. The real normalizer is used on purpose:
     * what is under test is that the input reaches the repository in canonical form
     *
     * @param array<string, GiftCardInterface|null> $giftCardsByCode
     */
    private function transformer(array $giftCardsByCode, LoggerInterface $logger = new NullLogger()): GiftCardToCodeDataTransformer
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        $repository = $this->prophesize(GiftCardRepositoryInterface::class);
        foreach ($giftCardsByCode as $code => $giftCard) {
            $repository->findOneEnabledByCodeAndChannel($code, $channel)->willReturn($giftCard);
        }

        return new GiftCardToCodeDataTransformer(
            $repository->reveal(),
            $channelContext->reveal(),
            new GiftCardCodeNormalizer(),
            $logger,
        );
    }
}
