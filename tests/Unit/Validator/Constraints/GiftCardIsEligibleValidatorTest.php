<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardIneligibilityReason;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligible;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligibleValidator;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardIsEligibleValidator>
 */
final class GiftCardIsEligibleValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<OrderInterface> */
    private ObjectProphecy $order;

    /** @var ObjectProphecy<GiftCardEligibilityCheckerInterface> */
    private ObjectProphecy $eligibilityChecker;

    /** @var ObjectProphecy<LoggerInterface> */
    private ObjectProphecy $logger;

    protected function setUp(): void
    {
        $this->order = $this->prophesize(OrderInterface::class);
        $this->order->hasGiftCard(Argument::any())->willReturn(false);

        $this->eligibilityChecker = $this->prophesize(GiftCardEligibilityCheckerInterface::class);
        $this->eligibilityChecker->getIneligibilityReason(Argument::cetera())->willReturn(null);

        $this->logger = $this->prophesize(LoggerInterface::class);

        parent::setUp();
    }

    /** @test */
    public function it_does_not_add_a_violation_for_a_gift_card_that_can_be_used(): void
    {
        $this->validator->validate($this->giftCard(), new GiftCardIsEligible());

        $this->assertNoViolation();
    }

    /**
     * The customer is told no more than that the card cannot be applied, whatever the reason, because a reason
     * would confirm that the code they entered belongs to a real gift card
     *
     * @dataProvider provideIneligibilityReasons
     *
     * @test
     */
    public function it_keeps_the_reason_a_gift_card_cannot_be_used_to_the_log(GiftCardIneligibilityReason $reason): void
    {
        $giftCard = $this->giftCard();
        $this->eligibilityChecker->getIneligibilityReason($giftCard, $this->order->reveal())->willReturn($reason);

        $this->validator->validate($giftCard, new GiftCardIsEligible());

        $this->buildViolation('setono_sylius_gift_card.gift_card.could_not_be_applied')->assertRaised();

        $this->logger->info(Argument::containingString($reason->value))->shouldHaveBeenCalled();
    }

    /**
     * The card exists and may well be spendable, in another channel for instance, so the log line names it by a
     * masked code
     *
     * @test
     */
    public function it_does_not_write_the_code_of_the_gift_card_to_the_log(): void
    {
        $giftCard = $this->giftCard();
        $this->eligibilityChecker->getIneligibilityReason($giftCard, $this->order->reveal())
            ->willReturn(GiftCardIneligibilityReason::ChannelMismatch);

        $this->validator->validate($giftCard, new GiftCardIsEligible());

        $this->logger->info(Argument::that(static fn (string $message): bool => str_contains($message, '"********CARD"') && !str_contains($message, 'SOMEGIFTCARD')))
            ->shouldHaveBeenCalledOnce();
    }

    /**
     * @return iterable<string, array{GiftCardIneligibilityReason}>
     */
    public static function provideIneligibilityReasons(): iterable
    {
        foreach (GiftCardIneligibilityReason::cases() as $reason) {
            yield $reason->value => [$reason];
        }
    }

    /**
     * The exception: a card that is already on the customer's own order tells them nothing they did not
     * already know, and leaving them guessing would be needlessly unhelpful
     *
     * @test
     */
    public function it_says_when_the_gift_card_is_already_on_the_order(): void
    {
        $giftCard = $this->giftCard();
        $this->order->hasGiftCard($giftCard)->willReturn(true);

        $this->validator->validate($giftCard, new GiftCardIsEligible());

        $this->buildViolation('setono_sylius_gift_card.gift_card.already_applied')->assertRaised();
    }

    protected function createValidator(): GiftCardIsEligibleValidator
    {
        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($this->order->reveal());

        return new GiftCardIsEligibleValidator($cartContext->reveal(), $this->eligibilityChecker->reveal(), new GiftCardCodeNormalizer(), $this->logger->reveal());
    }

    private function giftCard(): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('SOMEGIFTCARD');
        $giftCard->setEnabled(true);
        $giftCard->setAmount(1000);

        return $giftCard;
    }
}
