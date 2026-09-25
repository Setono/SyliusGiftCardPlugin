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
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The amount a customer may buy a gift card for is bounded by the limits of the channel being shopped in, and the
 * violation quotes the limit that was crossed as money in the channel's base currency, formatted in the locale being
 * browsed the way the amount field's help text quotes it
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

    /** @var ObjectProphecy<LocaleContextInterface> */
    private ObjectProphecy $localeContext;

    /**
     * @test
     *
     * @dataProvider minimumsInTheLocaleBrowsed
     */
    public function it_rejects_an_amount_below_the_minimum(string $localeCode, string $minimum): void
    {
        $this->channelLimits(100, 50000);
        $this->localeContext->getLocaleCode()->willReturn($localeCode);

        $this->validator->validate(99, new ValidGiftCardAmount());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.amount.too_low')
            ->setParameter('{{ minimum }}', $minimum)
            ->assertRaised();
    }

    /**
     * @test
     *
     * @dataProvider maximumsInTheLocaleBrowsed
     */
    public function it_rejects_an_amount_above_the_maximum(string $localeCode, string $maximum): void
    {
        $this->channelLimits(100, 50000);
        $this->localeContext->getLocaleCode()->willReturn($localeCode);

        $this->validator->validate(50001, new ValidGiftCardAmount());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.amount.too_high')
            ->setParameter('{{ maximum }}', $maximum)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function minimumsInTheLocaleBrowsed(): iterable
    {
        yield 'browsing in French' => ['fr_FR', '1,00 DKK'];
        yield 'browsing in Danish' => ['da_DK', '1,00 kr.'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function maximumsInTheLocaleBrowsed(): iterable
    {
        yield 'browsing in French' => ['fr_FR', '500,00 DKK'];
        yield 'browsing in Danish' => ['da_DK', '500,00 kr.'];
    }

    /**
     * Validation also runs where no locale is being browsed, such as a console command or a host application's
     * API. The limit is then quoted the way the money formatter quotes it without a locale instead of the
     * validation failing
     *
     * @test
     */
    public function it_quotes_the_limit_without_a_locale_when_none_can_be_resolved(): void
    {
        $this->channelLimits(100, 50000);
        $this->localeContext->getLocaleCode()->willThrow(new LocaleNotFoundException());

        $this->validator->validate(99, new ValidGiftCardAmount());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.amount.too_low')
            ->setParameter('{{ minimum }}', 'DKK 1.00')
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
        $this->localeContext = $this->prophesize(LocaleContextInterface::class);

        // What Sylius' money formatter makes of the limits in each locale, and in English without one
        $moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $moneyFormatter->format(100, 'DKK', 'fr_FR')->willReturn('1,00 DKK');
        $moneyFormatter->format(50000, 'DKK', 'fr_FR')->willReturn('500,00 DKK');
        $moneyFormatter->format(100, 'DKK', 'da_DK')->willReturn('1,00 kr.');
        $moneyFormatter->format(50000, 'DKK', 'da_DK')->willReturn('500,00 kr.');
        $moneyFormatter->format(100, 'DKK', null)->willReturn('DKK 1.00');

        return new ValidGiftCardAmountValidator(
            $this->channelContext->reveal(),
            $this->amountLimitsProvider->reveal(),
            $moneyFormatter->reveal(),
            $this->localeContext->reveal(),
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
