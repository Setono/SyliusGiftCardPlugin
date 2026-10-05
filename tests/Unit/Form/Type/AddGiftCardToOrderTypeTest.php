<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardIneligibilityReason;
use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderCommand;
use Setono\SyliusGiftCardPlugin\Form\DataTransformer\GiftCardToCodeDataTransformer;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligible;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligibleValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * The cart's gift card form takes the code a customer types and puts the gift card behind it on the command. The PDF
 * prints the code grouped by dashes and customers type what they see, so the card is looked up by the normalized code
 */
final class AddGiftCardToOrderTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    /** The code the gift card is stored under, which is the only one the repository answers to */
    private const CODE = 'ABCDEFGHJKMNPQRS';

    private GiftCardInterface $giftCard;

    /** @var ObjectProphecy<GiftCardEligibilityCheckerInterface> */
    private ObjectProphecy $eligibilityChecker;

    /**
     * The ways a code can be written are the transformer's own test; this is about the form handing it what was typed
     *
     * @test
     */
    public function it_puts_the_gift_card_behind_the_code_as_printed_on_the_command(): void
    {
        $command = new AddGiftCardToOrderCommand();

        $form = $this->createForm($command);
        $form->submit(['giftCard' => 'abcd-efgh-jkmn-pqrs']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($this->giftCard, $command->getGiftCard());
    }

    /**
     * The empty command a code without a gift card leaves behind would fail the NotBlank constraint as well. Reported on
     * top, "enter a code" would set an unknown code apart from a code that exists but cannot be used, which gets the
     * generic message alone
     *
     * @test
     */
    public function it_refuses_a_code_no_gift_card_has_with_the_generic_message_alone(): void
    {
        $command = new AddGiftCardToOrderCommand();

        $form = $this->createForm($command);
        $form->submit(['giftCard' => 'nosu-chca-rd00-0000']);

        self::assertFalse($form->get('giftCard')->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame(['setono_sylius_gift_card.gift_card.could_not_be_applied'], self::messages($form->get('giftCard')));
        self::assertCount(1, $form->getErrors(true));
        self::assertNull($command->getGiftCard());
    }

    /**
     * A code that matches no gift card fails in the transformer, one that matches a card that cannot be used fails
     * the eligibility constraint. Were the two messages to differ, the form would tell a code guesser which codes
     * exist
     *
     * @test
     */
    public function it_refuses_an_unknown_code_in_the_words_it_refuses_an_unusable_gift_card(): void
    {
        $this->eligibilityChecker
            ->getIneligibilityReason($this->giftCard, Argument::any())
            ->willReturn(GiftCardIneligibilityReason::Expired);

        $unusable = $this->createForm(new AddGiftCardToOrderCommand());
        $unusable->submit(['giftCard' => self::CODE]);

        // the card was found, so it is the eligibility constraint speaking
        self::assertTrue($unusable->isSynchronized());
        self::assertSame([(new GiftCardIsEligible())->message], self::messages($unusable->get('giftCard')));

        $unknown = $this->createForm(new AddGiftCardToOrderCommand());
        $unknown->submit(['giftCard' => 'NOSUCHCARD000000']);

        self::assertSame(self::messages($unusable->get('giftCard')), self::messages($unknown->get('giftCard')));
    }

    /**
     * The constraints on the command are declared in the group the form is configured with, so this is also what
     * tells that the form validates in the right group
     *
     * @test
     */
    public function it_asks_for_a_code_when_none_is_entered(): void
    {
        $form = $this->createForm(new AddGiftCardToOrderCommand());
        $form->submit(['giftCard' => '']);

        self::assertTrue($form->isSynchronized());
        self::assertSame(
            ['setono_sylius_gift_card.add_gift_card_to_order_command.gift_card.not_blank'],
            self::messages($form->get('giftCard')),
        );
    }

    /**
     * @return FormInterface<AddGiftCardToOrderCommand>
     */
    private function createForm(AddGiftCardToOrderCommand $command): FormInterface
    {
        /** @var FormInterface<AddGiftCardToOrderCommand> $form */
        $form = $this->factory->create(AddGiftCardToOrderType::class, $command);

        return $form;
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private static function messages(FormInterface $form): array
    {
        $messages = [];
        foreach ($form->getErrors() as $error) {
            self::assertInstanceOf(FormError::class, $error);
            $messages[] = $error->getMessage();
        }

        return $messages;
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        $this->giftCard = new GiftCard();
        $this->giftCard->setCode(self::CODE);

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        // Only the canonical code finds the card, so a lookup with the code as typed finds nothing
        $repository = $this->prophesize(GiftCardRepositoryInterface::class);
        $repository->findOneEnabledByCodeAndChannel(Argument::type('string'), $channel)->willReturn(null);
        $repository->findOneEnabledByCodeAndChannel(self::CODE, $channel)->willReturn($this->giftCard);

        $order = $this->prophesize(OrderInterface::class);
        $order->hasGiftCard(Argument::any())->willReturn(false);

        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($order->reveal());

        $this->eligibilityChecker = $this->prophesize(GiftCardEligibilityCheckerInterface::class);
        $this->eligibilityChecker->getIneligibilityReason(Argument::cetera())->willReturn(null);

        $normalizer = new GiftCardCodeNormalizer();

        $type = new AddGiftCardToOrderType(
            new GiftCardToCodeDataTransformer($repository->reveal(), $channelContext->reveal(), $normalizer),
            ['setono_sylius_gift_card'],
        );

        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/AddGiftCardToOrderCommand.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                GiftCardIsEligibleValidator::class => new GiftCardIsEligibleValidator(
                    $cartContext->reveal(),
                    $this->eligibilityChecker->reveal(),
                    $normalizer,
                ),
            ]))
            ->getValidator()
        ;

        return [
            new PreloadedExtension([$type], []),
            new ValidatorExtension($validator),
        ];
    }
}
