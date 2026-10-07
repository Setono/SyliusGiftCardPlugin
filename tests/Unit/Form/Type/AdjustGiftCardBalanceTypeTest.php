<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\AdjustGiftCardBalanceCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AdjustGiftCardBalanceType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\BalanceAdjustmentIsEligibleValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * The admin types the adjustment in major units of the card's currency, positive to add and negative to deduct; the
 * command carries it in minor units, the unit of the balance and of every ledger row
 */
final class AdjustGiftCardBalanceTypeTest extends TypeTestCase
{
    use FormErrorsTrait;
    use ProphecyTrait;

    /** @test */
    public function it_binds_a_deduction_in_minor_units_to_the_command(): void
    {
        $giftCard = $this->giftCard();
        $command = new AdjustGiftCardBalanceCommand($giftCard);

        $form = $this->factory->create(AdjustGiftCardBalanceType::class, $command, ['currency' => 'DKK']);
        $form->submit([
            'amount' => '-12.50',
            'reason' => 'Customer returned part of the goods',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame($command, $form->getData());
        self::assertSame(-1250, $command->getAmount());
        self::assertSame('Customer returned part of the goods', $command->getReason());
        self::assertSame($giftCard, $command->getGiftCard());
        self::assertSame(5000, $giftCard->getAmount(), 'the form describes the adjustment, the balance operator applies it');
    }

    /** @test */
    public function it_binds_an_addition_in_minor_units_to_the_command(): void
    {
        $command = new AdjustGiftCardBalanceCommand($this->giftCard());

        $form = $this->factory->create(AdjustGiftCardBalanceType::class, $command, ['currency' => 'DKK']);
        $form->submit(['amount' => '7.25', 'reason' => 'Goodwill']);

        self::assertTrue($form->isSynchronized());
        self::assertSame(725, $command->getAmount());
    }

    /** @test */
    public function it_shows_the_amount_in_the_currency_of_the_gift_card(): void
    {
        $form = $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()), [
            'currency' => 'DKK',
        ]);

        $vars = $form->createView()->children['amount']->vars;
        self::assertIsArray($vars);
        self::assertSame('DKK', $vars['currency']);
    }

    /**
     * Whatever currency the form fell back on, a caller that left the option out would show the amount of a card in any
     * other currency in that one, and nothing would fail
     *
     * @test
     */
    public function it_cannot_be_built_without_the_currency_of_the_gift_card(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('"currency"');

        $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()));
    }

    /**
     * A card's currency code is nullable, so a caller handing it on as it is passes null for a card without one
     *
     * @test
     */
    public function it_refuses_a_currency_that_is_not_a_code(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('"currency"');

        $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()), [
            'currency' => null,
        ]);
    }

    /**
     * The constraints live on the command, in the group the form validates. A form validating any other group would
     * let every adjustment through, and a blank amount has to reach them as null rather than end the request in a 500
     *
     * @test
     *
     * @dataProvider invalidAdjustments
     *
     * @param array<string, string> $submitted
     */
    public function it_reports_an_invalid_adjustment_on_its_field(array $submitted, string $field, string $message): void
    {
        $form = $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()), [
            'currency' => 'DKK',
        ]);
        $form->submit($submitted);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame([$message], self::errorMessageTemplates($form->get($field)));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function invalidAdjustments(): iterable
    {
        yield 'a blank amount' => [
            ['amount' => '', 'reason' => 'Goodwill'],
            'amount',
            'setono_sylius_gift_card.adjust_gift_card_balance_command.amount.not_blank',
        ];

        yield 'an adjustment of nothing' => [
            ['amount' => '0', 'reason' => 'Goodwill'],
            'amount',
            'setono_sylius_gift_card.adjust_gift_card_balance_command.amount.not_zero',
        ];

        yield 'no reason' => [
            ['amount' => '5', 'reason' => ''],
            'reason',
            'setono_sylius_gift_card.adjust_gift_card_balance_command.reason.not_blank',
        ];
    }

    /**
     * Deducting more than the card holds would reach the balance operator, which asserts and ends the request in a
     * 500. The admin is told the balance, in the card's currency, on the amount field instead
     *
     * @test
     */
    public function it_lets_the_whole_balance_be_deducted_but_no_more(): void
    {
        $form = $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()), [
            'currency' => 'DKK',
        ]);
        $form->submit(['amount' => '-50.00', 'reason' => 'Spent in the physical store']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $form = $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()), [
            'currency' => 'DKK',
        ]);
        $form->submit(['amount' => '-50.01', 'reason' => 'Spent in the physical store']);

        self::assertFalse($form->isValid());
        $errors = iterator_to_array($form->get('amount')->getErrors());
        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);
        self::assertSame('setono_sylius_gift_card.gift_card.adjustment_makes_balance_negative', $errors[0]->getMessageTemplate());
        self::assertSame(['{{ balance }}' => 'DKK 50.00'], $errors[0]->getMessageParameters());
    }

    /**
     * The balance is kept in an integer column, a signed 32-bit integer, so a card holds at most 21474836.47. Adding
     * more used to reach the database, which refused it with a 500. The admin is told the most a card holds and the
     * balance instead
     *
     * @test
     */
    public function it_lets_the_balance_be_raised_to_the_most_a_card_can_hold_but_no_more(): void
    {
        $command = new AdjustGiftCardBalanceCommand($this->giftCard());
        $form = $this->factory->create(AdjustGiftCardBalanceType::class, $command, ['currency' => 'DKK']);
        $form->submit(['amount' => '21474786.47', 'reason' => 'Corporate gift']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(2147478647, $command->getAmount());

        $form = $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()), [
            'currency' => 'DKK',
        ]);
        $form->submit(['amount' => '21474786.48', 'reason' => 'Corporate gift']);

        self::assertFalse($form->isValid());
        $errors = self::errors($form->get('amount'));
        self::assertCount(1, $errors);
        self::assertSame('setono_sylius_gift_card.gift_card.adjustment_makes_balance_too_large', $errors[0]->getMessageTemplate());
        self::assertSame(['{{ balance }}' => 'DKK 50.00', '{{ maximum }}' => 'DKK 21,474,836.47'], $errors[0]->getMessageParameters());
    }

    /**
     * The minor units of this amount are beyond PHP's integer range, where Sylius' money field wraps them around: it
     * used to add 4,464.64 to the balance. The field cannot read it instead
     *
     * @test
     */
    public function it_refuses_an_amount_whose_minor_units_php_cannot_hold(): void
    {
        $command = new AdjustGiftCardBalanceCommand($this->giftCard());

        $form = $this->factory->create(AdjustGiftCardBalanceType::class, $command, ['currency' => 'DKK']);
        $form->submit(['amount' => '184467440737095560', 'reason' => 'Goodwill']);

        self::assertFalse($form->get('amount')->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertNull($command->getAmount());
        self::assertSame(['Please enter a valid money amount.'], self::errorMessageTemplates($form->get('amount')));
    }

    private function giftCard(): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCurrencyCode('DKK');
        $giftCard->setAmount(5000);

        return $giftCard;
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $moneyFormatter->format(5000, 'DKK')->willReturn('DKK 50.00');
        $moneyFormatter->format(2147483647, 'DKK')->willReturn('DKK 21,474,836.47');

        // The rules live in the command's validation mapping rather than on the form, so the mapping is what is loaded
        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/AdjustGiftCardBalanceCommand.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                BalanceAdjustmentIsEligibleValidator::class => new BalanceAdjustmentIsEligibleValidator($moneyFormatter->reveal()),
            ]))
            ->getValidator()
        ;

        return [new ValidatorExtension($validator)];
    }
}
