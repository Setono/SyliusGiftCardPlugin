<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimits;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmount;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmountValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\Currency;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The amount a customer may buy a gift card for is bounded by the limits of the channel being shopped in, and the
 * violation quotes the limit that was crossed as money in the channel's base currency
 *
 * @extends ConstraintValidatorTestCase<ValidGiftCardAmountValidator>
 */
final class ValidGiftCardAmountValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<GiftCardAmountLimitsProviderInterface> */
    private ObjectProphecy $amountLimitsProvider;

    /** @test */
    public function it_rejects_an_amount_below_the_minimum(): void
    {
        $this->channelLimits(100, 50000);

        $this->validator->validate(99, new ValidGiftCardAmount());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.amount.too_low')
            ->setParameter('{{ minimum }}', 'DKK 1.00')
            ->assertRaised();
    }

    /** @test */
    public function it_rejects_an_amount_above_the_maximum(): void
    {
        $this->channelLimits(100, 50000);

        $this->validator->validate(50001, new ValidGiftCardAmount());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.amount.too_high')
            ->setParameter('{{ maximum }}', 'DKK 500.00')
            ->assertRaised();
    }

    /**
     * @test
     *
     * @dataProvider amountsWithinTheLimits
     */
    public function it_accepts_an_amount_within_the_limits(int $amount): void
    {
        $this->channelLimits(100, 50000);

        $this->validator->validate($amount, new ValidGiftCardAmount());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function amountsWithinTheLimits(): iterable
    {
        yield 'the minimum itself' => [100];
        yield 'an amount in between' => [2500];
        yield 'the maximum itself' => [50000];
    }

    /** @test */
    public function it_accepts_any_amount_above_the_minimum_when_there_is_no_maximum(): void
    {
        $this->channelLimits(100, null);

        $this->validator->validate(\PHP_INT_MAX, new ValidGiftCardAmount());

        $this->assertNoViolation();
    }

    /**
     * A blank amount is NotBlank's to report, which the mapping puts in front of this constraint
     *
     * @test
     */
    public function it_leaves_a_blank_amount_to_the_not_blank_constraint(): void
    {
        $this->validator->validate(null, new ValidGiftCardAmount());

        $this->assertNoViolation();
        $this->channelContext->getChannel()->shouldNotHaveBeenCalled();
    }

    /** @test */
    public function it_has_no_limits_to_enforce_outside_a_channel(): void
    {
        $this->channelContext->getChannel()->willThrow(new ChannelNotFoundException());

        $this->validator->validate(1, new ValidGiftCardAmount());

        $this->assertNoViolation();
    }

    /**
     * The limits provider is only defined for the core channel, which carries the base currency the limits are
     * quoted in
     *
     * @test
     */
    public function it_has_no_limits_to_enforce_in_a_channel_that_is_not_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());
        $this->amountLimitsProvider->getLimits(Argument::any())->shouldNotBeCalled();

        $this->validator->validate(1, new ValidGiftCardAmount());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_amounts_in_minor_units(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('25.00', new ValidGiftCardAmount());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(2500, new NotBlank());
    }

    protected function createValidator(): ValidGiftCardAmountValidator
    {
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->amountLimitsProvider = $this->prophesize(GiftCardAmountLimitsProviderInterface::class);

        $moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $moneyFormatter->format(100, 'DKK')->willReturn('DKK 1.00');
        $moneyFormatter->format(50000, 'DKK')->willReturn('DKK 500.00');

        return new ValidGiftCardAmountValidator(
            $this->channelContext->reveal(),
            $this->amountLimitsProvider->reveal(),
            $moneyFormatter->reveal(),
        );
    }

    private function channelLimits(int $minimum, ?int $maximum): void
    {
        $currency = new Currency();
        $currency->setCode('DKK');

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getBaseCurrency()->willReturn($currency);

        $this->channelContext->getChannel()->willReturn($channel->reveal());
        $this->amountLimitsProvider->getLimits($channel->reveal())->willReturn(new GiftCardAmountLimits($minimum, $maximum));
    }
}
