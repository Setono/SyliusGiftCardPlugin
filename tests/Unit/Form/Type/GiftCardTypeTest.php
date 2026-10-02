<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Form\Type\CustomerAutocompleteChoiceType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCodeLengthValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GiftCardTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    private ChannelInterface $channel;

    /** @test */
    public function it_maps_a_valid_submission_and_seeds_the_initial_amount(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit([
            'code' => 'GIFTCARDCODE',
            'channel' => 'WEB',
            'currencyCode' => 'DKK',
            'amount' => '50',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        // The admin types major units; the model keeps minor units
        self::assertSame(5000, $giftCard->getAmount());
        self::assertSame(5000, $giftCard->getInitialAmount());
    }

    /**
     * The data mapper writes the submitted amount into the setter during submit(), before the POST_SUBMIT
     * validation listener runs, so a blank amount used to end the request in a 500. The setter now accepts null and
     * the NotBlank constraint reports it
     *
     * @test
     */
    public function it_reports_a_blank_amount_as_a_validation_error(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit([
            'code' => 'GIFTCARDCODE',
            'amount' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame(0, $giftCard->getAmount(), 'a card without an amount reports an empty balance');
        self::assertCount(1, $form->get('amount')->getErrors());
        self::assertSame('setono_sylius_gift_card.gift_card.amount.not_blank', $form->get('amount')->getErrors()[0]->getMessage());
    }

    /** @test */
    public function it_lets_the_currency_be_chosen_while_the_card_is_new(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertTrue($form->has('channel'));
        self::assertTrue($form->has('currencyCode'));
        self::assertFalse($form->get('currencyCode')->isDisabled());
        // The channel's base currency is offered first
        self::assertSame(['DKK'], $form->get('currencyCode')->getConfig()->getOption('preferred_choices'));

        $form->submit([
            'code' => 'NEWCARD',
            'channel' => 'WEB',
            'currencyCode' => 'EUR',
            'amount' => '100',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('EUR', $giftCard->getCurrencyCode());
    }

    /** @test */
    public function it_shows_the_currency_but_locks_it_once_the_card_exists(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);
        $giftCard->setCurrencyCode('DKK');
        $giftCard->setCode('EXISTING');
        (new \ReflectionProperty(GiftCard::class, 'id'))->setValue($giftCard, 1);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertFalse($form->has('channel'));
        self::assertTrue($form->has('currencyCode'));
        self::assertTrue($form->get('currencyCode')->isDisabled());

        // A disabled field ignores whatever is submitted for it, so the card keeps the currency it was issued in
        $form->submit([
            'currencyCode' => 'EUR',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('DKK', $giftCard->getCurrencyCode());
    }

    /**
     * After issuance the balance belongs to the balance operator, which records every movement in the ledger. Editing
     * a card must neither offer the amount nor move what it was issued with
     *
     * @test
     */
    public function it_keeps_the_balance_out_of_the_form_once_the_card_exists(): void
    {
        $giftCard = $this->existingGiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(3000);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertFalse($form->has('amount'));

        $form->submit([
            'customMessage' => 'Enjoy',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('Enjoy', $giftCard->getCustomMessage());
        self::assertSame(3000, $giftCard->getAmount());
        self::assertSame(5000, $giftCard->getInitialAmount());
    }

    /**
     * Whether to email the customer is decided when the card is issued; an existing card is sent from its show page
     *
     * @test
     */
    public function it_only_asks_whether_to_notify_the_customer_while_the_card_is_new(): void
    {
        self::assertTrue($this->factory->create(GiftCardType::class, new GiftCard())->has('sendNotificationEmail'));
        self::assertFalse($this->factory->create(GiftCardType::class, $this->existingGiftCard())->has('sendNotificationEmail'));
    }

    /** @test */
    public function it_generates_a_code_for_a_card_that_has_none(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertSame('GENERATEDCODE', $giftCard->getCode());
        self::assertSame('GENERATEDCODE', $form->get('code')->getData());
    }

    /** @test */
    public function it_keeps_the_code_a_card_already_has(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('CHOSENCODE');

        $this->factory->create(GiftCardType::class, $giftCard);

        self::assertSame('CHOSENCODE', $giftCard->getCode());
    }

    /**
     * The factory gives every new card a code, and Sylius builds another card, with another code, on the POST. The
     * code shown on the form has to be submitted, or the card is saved under a code the admin never saw
     *
     * @test
     */
    public function it_issues_a_new_card_with_the_code_its_form_shows(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('SHOWNCODE1234');

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertFalse($form->get('code')->isDisabled());
        self::assertSame('SHOWNCODE1234', $form->get('code')->getViewData());
        self::assertSame('setono_sylius_gift_card.form.gift_card.code_help', $form->get('code')->getConfig()->getOption('help'));
        self::assertSame(['%minimum%' => 12], $form->get('code')->getConfig()->getOption('help_translation_parameters'));

        $submitted = new GiftCard();
        $submitted->setCode('GENERATEDONPOST');

        $form = $this->factory->create(GiftCardType::class, $submitted);
        $form->submit($this->validSubmission(['code' => 'SHOWNCODE1234']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('SHOWNCODE1234', $submitted->getCode());
    }

    /**
     * The cart normalizes what a customer types before looking the code up, so a code has to be stored normalized
     *
     * @test
     */
    public function it_normalizes_a_code_the_admin_types(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GENERATEDONPOST');

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'summer-2026 xyz']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('SUMMER2026XYZ', $giftCard->getCode());
        // an invalid form is rendered again with the code the card would have been given
        self::assertSame('SUMMER2026XYZ', $form->get('code')->getViewData());
    }

    /** @test */
    public function it_reports_a_code_with_nothing_left_once_normalized_as_blank(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GENERATEDONPOST');

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => ' -- ']));

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('code')->getErrors());
        self::assertSame('setono_sylius_gift_card.gift_card.code.not_blank', $form->get('code')->getErrors()[0]->getMessage());
    }

    /**
     * A code is a bearer token, and a short one can be guessed at the redemption form. What counts is what is left once
     * the code is normalized, which is what the cart looks up
     *
     * @test
     */
    public function it_refuses_a_typed_code_shorter_than_the_minimum(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GENERATEDONPOST');

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        // 14 characters as typed, 11 once the dashes are dropped
        $form->submit($this->validSubmission(['code' => 'ABCD-EFGH-JKM']));

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('code')->getErrors());
        self::assertSame('setono_sylius_gift_card.gift_card.code.too_short', $form->get('code')->getErrors()[0]->getMessage());

        $giftCard = new GiftCard();
        $giftCard->setCode('GENERATEDONPOST');

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'ABCD-EFGH-JKMN']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('ABCDEFGHJKMN', $giftCard->getCode());
    }

    /**
     * Cards brought over from 0.12 may have shorter codes. They are validated whenever they are edited, and must stay
     * editable
     *
     * @test
     */
    public function it_lets_an_existing_card_with_a_shorter_code_be_edited(): void
    {
        $giftCard = $this->existingGiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        self::assertLessThan(12, strlen((string) $giftCard->getCode()));

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit(['customMessage' => 'Happy birthday']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('Happy birthday', $giftCard->getCustomMessage());
    }

    /**
     * The browser submits every line break of the textarea as CR LF, so the admin is held to the same message length
     * as the customer only if a line break counts once. Symfony's TextareaType normalizes line breaks itself since
     * symfony/form 6.4.31; this holds for the earlier 6.4 releases the plugin allows as well (CI's lowest dependencies)
     *
     * @test
     */
    public function it_counts_a_line_break_in_the_message_as_one_character_the_way_the_browser_does(): void
    {
        // 195 characters on 6 lines: 200 characters by the browser's count, 205 as submitted
        $lines = str_split(str_repeat('a', 195), 33);
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission([
            'code' => 'GIFTCARDCODE',
            'customMessage' => implode("\r\n", $lines),
        ]));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(implode("\n", $lines), $giftCard->getCustomMessage());

        $form = $this->factory->create(GiftCardType::class, new GiftCard());
        $form->submit($this->validSubmission([
            'code' => 'GIFTCARDCODE',
            'customMessage' => implode("\r\n", $lines) . 'a',
        ]));

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('customMessage')->getErrors());
    }

    /**
     * Once issued, the code is what the customer was given
     *
     * @test
     */
    public function it_shows_the_code_but_locks_it_once_the_card_exists(): void
    {
        $giftCard = $this->existingGiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertTrue($form->get('code')->isDisabled());
        self::assertSame('EXISTING', $form->get('code')->getViewData());
        self::assertNull($form->get('code')->getConfig()->getOption('help'));

        $form->submit([
            'code' => 'REPLACED',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('EXISTING', $giftCard->getCode());
    }

    /**
     * The admin picks a date, and a gift card stays valid through the end of that day
     *
     * @test
     */
    public function it_keeps_the_card_valid_through_the_end_of_the_expiry_day(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit([
            'code' => 'EXPIRING',
            'channel' => 'WEB',
            'currencyCode' => 'DKK',
            'amount' => '50',
            'expiresAt' => '2031-03-15',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('2031-03-15 23:59:59', $giftCard->getExpiresAt()?->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_leaves_a_card_without_an_expiry_date_valid_indefinitely(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setExpiresAt(new \DateTimeImmutable('2031-03-15 23:59:59'));

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit([
            'code' => 'FOREVER',
            'channel' => 'WEB',
            'currencyCode' => 'DKK',
            'amount' => '50',
            'expiresAt' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertNull($giftCard->getExpiresAt());
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    private function validSubmission(array $fields): array
    {
        return $fields + [
            'channel' => 'WEB',
            'currencyCode' => 'DKK',
            'amount' => '50',
        ];
    }

    private function existingGiftCard(): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);
        $giftCard->setCurrencyCode('DKK');
        $giftCard->setCode('EXISTING');
        (new \ReflectionProperty(GiftCard::class, 'id'))->setValue($giftCard, 1);

        return $giftCard;
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $this->channel = $this->channel();

        /** @var ObjectProphecy<RepositoryInterface<CurrencyInterface>> $currencyRepositoryProphecy */
        $currencyRepositoryProphecy = $this->prophesize(RepositoryInterface::class);
        $currencyRepositoryProphecy->findAll()->willReturn([$this->currency('DKK'), $this->currency('EUR')]);
        $currencyRepository = $currencyRepositoryProphecy->reveal();

        $codeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $codeGenerator->generate()->willReturn('GENERATEDCODE');

        $type = new GiftCardType(
            GiftCard::class,
            $currencyRepository,
            $codeGenerator->reveal(),
            new GiftCardCodeNormalizer(),
            12,
            ['setono_sylius_gift_card'],
        );

        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository->findAll()->willReturn([$this->channel]);

        $customerRepository = $this->prophesize(RepositoryInterface::class);

        $resourceRepositoryRegistry = $this->prophesize(ServiceRegistryInterface::class);
        $resourceRepositoryRegistry->get('sylius.customer')->willReturn($customerRepository->reveal());

        $preloaded = new PreloadedExtension([
            $type,
            new ChannelChoiceType($channelRepository->reveal()),
            new CustomerAutocompleteChoiceType($this->prophesize(UrlGeneratorInterface::class)->reveal()),
            new ResourceAutocompleteChoiceType($resourceRepositoryRegistry->reveal()),
        ], []);

        return [$preloaded, new ValidatorExtension($this->createValidator())];
    }

    private function createValidator(): ValidatorInterface
    {
        $uniqueEntityValidator = new class() extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
                // Uniqueness of the code is a database lookup; this test only exercises the amount
            }
        };

        return Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCard.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                'doctrine.orm.validator.unique' => $uniqueEntityValidator,
                // built by the container with the configured limit; the mapping's default of 200 is used here
                GiftCardMessageLengthValidator::class => new GiftCardMessageLengthValidator(200),
                // built by the container with the configured minimum, 12 unless raised
                GiftCardCodeLengthValidator::class => new GiftCardCodeLengthValidator(12),
            ]))
            ->getValidator()
        ;
    }

    private function channel(): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setName('Web store');
        $channel->setBaseCurrency($this->currency('DKK'));

        return $channel;
    }

    private function currency(string $code): CurrencyInterface
    {
        $currency = new Currency();
        $currency->setCode($code);

        return $currency;
    }
}
