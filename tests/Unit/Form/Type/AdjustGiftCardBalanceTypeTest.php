<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\AdjustGiftCardBalanceCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AdjustGiftCardBalanceType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * The admin types the adjustment in major units of the card's currency, positive to add and negative to deduct; the
 * command carries it in minor units, the unit of the balance and of every ledger row
 */
final class AdjustGiftCardBalanceTypeTest extends TypeTestCase
{
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
     * The constraints on the command are declared in this group, so a form validating any other group would let
     * every adjustment through
     *
     * @test
     */
    public function it_validates_the_command_with_the_plugins_constraints(): void
    {
        $form = $this->factory->create(AdjustGiftCardBalanceType::class, new AdjustGiftCardBalanceCommand($this->giftCard()));

        self::assertSame(['setono_sylius_gift_card'], $form->getConfig()->getOption('validation_groups'));
        self::assertSame(AdjustGiftCardBalanceCommand::class, $form->getConfig()->getDataClass());
    }

    private function giftCard(): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCurrencyCode('DKK');
        $giftCard->setAmount(5000);

        return $giftCard;
    }
}
