<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Form\Type\CustomerAutocompleteChoiceType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProvider;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCodeLengthValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsOfferedByChannelValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignIsAvailableInChannelValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Context\CompositeCurrencyContext;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormView;
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
    use FormErrorsTrait;
    use ProphecyTrait;

    private ChannelInterface $channel;

    private GiftCardCurrencyProvider $currencyProvider;

    /** @var ObjectProphecy<RepositoryInterface<CustomerInterface>> */
    private ObjectProphecy $customerRepository;

    /** Offered in the web store only */
    private GiftCardDesignInterface $classic;

    /** Offered in the web store and the outlet, and the one design with front artwork */
    private GiftCardDesignInterface $birthday;

    /** Offered in the outlet only */
    private GiftCardDesignInterface $christmas;

    /** Offered nowhere any longer */
    private GiftCardDesignInterface $retired;

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
     * Most amounts with cents have no exact binary representation: 0.29 * 100 is 28.999999999999996, so truncating
     * would issue the card one minor unit short
     *
     * @test
     *
     * @dataProvider amountsWithCents
     */
    public function it_issues_an_amount_with_cents_to_the_exact_minor_unit(string $typed, int $minorUnits): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'amount' => $typed]));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($minorUnits, $giftCard->getAmount());
        self::assertSame($minorUnits, $giftCard->getInitialAmount());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function amountsWithCents(): iterable
    {
        yield '0.29' => ['0.29', 29];
        yield '1.15' => ['1.15', 115];
        yield '19.99' => ['19.99', 1999];
        // 1.1 * 100 is 110.00000000000001, which rounding up would make 111
        yield '1.10' => ['1.10', 110];
    }

    /**
     * The balance is kept in an integer column, a signed 32-bit integer, so 21474836.47 is the most a card can hold.
     * More used to pass the form and end the request in a 500 when the database refused the card
     *
     * @test
     */
    public function it_issues_the_most_a_card_can_hold_and_refuses_more(): void
    {
        $giftCard = new GiftCard();
        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'amount' => '21474836.47']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(2147483647, $giftCard->getAmount());
        self::assertSame(2147483647, $giftCard->getInitialAmount());

        $form = $this->factory->create(GiftCardType::class, new GiftCard());
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'amount' => '21474836.48']));

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame(['setono_sylius_gift_card.gift_card.amount.too_large'], self::errorMessageTemplates($form->get('amount')));
    }

    /**
     * The minor units of these amounts are beyond PHP's integer range, where a cast wraps around: the first used to
     * issue a card holding 40.96. The field cannot read them instead, like any other number it cannot read
     *
     * @test
     *
     * @dataProvider amountsBeyondTheIntegerRange
     */
    public function it_refuses_an_amount_whose_minor_units_php_cannot_hold(string $typed): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'amount' => $typed]));

        self::assertFalse($form->get('amount')->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame(0, $giftCard->getAmount(), 'nothing reached the card');
        self::assertSame(['Please enter a number.'], self::errorMessageTemplates($form->get('amount')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsBeyondTheIntegerRange(): iterable
    {
        yield 'wrapping around to 40.96' => ['184467440737095560'];
        // Exactly 2^63 and -2^63 minor units: one more than the largest integer, and the smallest, which the number
        // field's own bounds leave out as well
        yield 'the first float beyond the largest integer' => ['92233720368547760'];
        yield 'its negative' => ['-92233720368547760'];
    }

    /**
     * The channel and the customer are submitted by code and email and must reach the card as the objects they name.
     * A new card is enabled and emailed to its customer unless the admin unticks those boxes, which the browser then
     * leaves out of the request
     *
     * @test
     */
    public function it_issues_a_new_card_the_way_the_admin_filled_it_in(): void
    {
        $customer = new Customer();
        $customer->setEmail('alice@example.com');
        $this->customerRepository->findOneBy(['email' => 'alice@example.com'])->willReturn($customer);

        $giftCard = new GiftCard();
        self::assertTrue($giftCard->isEnabled());
        self::assertTrue($giftCard->getSendNotificationEmail());

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission([
            'code' => 'GIFTCARDCODE',
            'customer' => 'alice@example.com',
            'customMessage' => 'Happy birthday',
        ]));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($this->channel, $giftCard->getChannel());
        self::assertSame($customer, $giftCard->getCustomer());
        self::assertSame('Happy birthday', $giftCard->getCustomMessage());
        self::assertFalse($giftCard->isEnabled());
        self::assertFalse($giftCard->getSendNotificationEmail());
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

    /**
     * A new card has its channel chosen on the same form, so it offers the currencies of every channel, each telling
     * which channels offer it and which have it as their base currency, for the template's script to narrow them to the
     * chosen channel's (#488)
     *
     * @test
     */
    public function it_lets_the_currency_be_chosen_while_the_card_is_new(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertTrue($form->has('channel'));
        self::assertTrue($form->has('currencyCode'));
        self::assertFalse($form->get('currencyCode')->isDisabled());
        self::assertSame([
            'DKK' => ['data-channels' => 'WEB', 'data-base-channels' => 'WEB'],
            'EUR' => ['data-channels' => 'WEB', 'data-base-channels' => ''],
            'SEK' => ['data-channels' => 'OUTLET', 'data-base-channels' => 'OUTLET'],
        ], self::choiceAttributes($form->createView()->children['currencyCode']));

        $form->submit([
            'code' => 'NEWCARD',
            'channel' => 'WEB',
            'currencyCode' => 'EUR',
            'amount' => '100',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('EUR', $giftCard->getCurrencyCode());
    }

    /**
     * A card is held in a currency its channel offers, its base currency or another, and pays converted into the order's
     * currency (#488). One its channel does not offer could never be redeemed, so the violation is put on the currency
     * field, where the admin chose it
     *
     * @test
     */
    public function it_refuses_a_currency_the_channel_does_not_offer(): void
    {
        $form = $this->factory->create(GiftCardType::class, new GiftCard());
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'currencyCode' => 'SEK']));

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.currency_code.not_offered_by_channel'],
            self::errorMessageTemplates($form->get('currencyCode')),
        );

        foreach (['DKK', 'EUR'] as $currencyCode) {
            $form = $this->factory->create(GiftCardType::class, new GiftCard());
            $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'currencyCode' => $currencyCode]));

            self::assertTrue($form->isValid(), $currencyCode . ': ' . (string) $form->getErrors(true));
        }
    }

    /**
     * The form starts a new card out at the base currency of the channel its channel field starts out at: the card's
     * channel, else the first channel it lists, so a card can be issued by filling in the amount alone (#358)
     *
     * @test
     */
    public function it_starts_a_new_card_at_the_base_currency_of_the_channel_the_form_starts_at(): void
    {
        self::assertSame('DKK', $this->currencyValue(new GiftCard()));

        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel('OUTLET', 'Outlet', 'SEK'));

        self::assertSame('SEK', $this->currencyValue($giftCard));
        self::assertNull($giftCard->getCurrencyCode(), 'the card itself is not given the currency before it is submitted');

        // a new card that already has a currency, given by an application's factory say, keeps it
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);
        $giftCard->setCurrencyCode('EUR');

        self::assertSame('EUR', $this->currencyValue($giftCard));
    }

    /**
     * Each currency is labelled by its code, and the help tells the admin what choosing one means while it can still be
     * chosen
     *
     * @test
     */
    public function it_labels_the_currencies_by_their_codes_and_explains_them_while_the_card_is_new(): void
    {
        $vars = $this->factory->create(GiftCardType::class, new GiftCard())->createView()->children['currencyCode']->vars;
        self::assertIsArray($vars);
        self::assertSame('sylius.ui.currency', $vars['label'] ?? null);
        self::assertSame('setono_sylius_gift_card.form.gift_card.currency_code_help', $vars['help'] ?? null);

        $labels = [];
        self::assertIsArray($vars['choices'] ?? null);
        foreach ($vars['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            $labels[] = $choice->label;
        }
        self::assertSame(['DKK', 'EUR', 'SEK'], $labels);

        $vars = $this->factory->create(GiftCardType::class, $this->existingGiftCard())->createView()->children['currencyCode']->vars;
        self::assertIsArray($vars);
        self::assertNull($vars['help'] ?? null);
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
        self::assertSame(['DKK' => []], self::choiceAttributes($form->createView()->children['currencyCode']));

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
     * as the customer only if a line break counts once. Symfony's TextareaType turns them into line feeds from
     * symfony/form 6.4.31 on, which the plugin requires for this; the test pins it, so the lowest dependencies CI
     * installs are held to it as well
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
     * Every card issued in the admin used to be virtual and printed with the default layout, and that is still what a
     * card gets unless the admin picks otherwise
     *
     * @test
     */
    public function it_issues_a_virtual_card_without_a_design_unless_told_otherwise(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $view = $form->createView();

        self::assertFalse($form->get('deliveryType')->isDisabled());
        $deliveryType = $view['deliveryType']->vars;
        self::assertIsArray($deliveryType);
        self::assertSame('setono_sylius_gift_card.ui.delivery_type', $deliveryType['label']);
        self::assertSame('virtual', $deliveryType['value']);
        self::assertSame('setono_sylius_gift_card.form.gift_card.delivery_type_help', $form->get('deliveryType')->getConfig()->getOption('help'));
        // each type is named by the label the grid and the show page use
        self::assertIsArray($deliveryType['choices']);
        $labels = [];
        foreach ($deliveryType['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->value);
            $labels[$choice->value] = $choice->label;
        }
        self::assertSame([
            'virtual' => 'setono_sylius_gift_card.ui.delivery_type_virtual',
            'physical' => 'setono_sylius_gift_card.ui.delivery_type_physical',
        ], $labels);

        self::assertFalse($form->get('design')->isRequired());
        self::assertSame('setono_sylius_gift_card.ui.design', $form->get('design')->getConfig()->getOption('label'));
        // the choice of no design is the one checked
        $none = $view['design']['placeholder']->vars;
        self::assertIsArray($none);
        self::assertSame('setono_sylius_gift_card.form.gift_card.no_design', $none['label']);
        self::assertTrue($none['checked']);

        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());
        self::assertNull($giftCard->getDesign());
    }

    /** @test */
    public function it_issues_a_card_with_the_design_and_delivery_type_the_admin_picks(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission([
            'code' => 'GIFTCARDCODE',
            'deliveryType' => 'physical',
            'design' => 'birthday',
        ]));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
        self::assertSame($this->birthday, $giftCard->getDesign());
    }

    /**
     * A blank delivery type means the default rather than a null the card cannot hold, which would end the request in
     * a 500, and one the card does not know is refused
     *
     * @test
     */
    public function it_takes_a_blank_delivery_type_for_the_default_and_refuses_an_unknown_one(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'deliveryType' => '']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());

        $giftCard = new GiftCard();
        $giftCard->setDeliveryType(GiftCardDeliveryType::Physical);

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'deliveryType' => 'pigeon']));

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('deliveryType')->getErrors());
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
    }

    /**
     * The channel is chosen on the same form, so the designs of every channel are offered, each telling which channels
     * offer it, for the template to narrow the picker to the chosen channel
     *
     * @test
     */
    public function it_offers_a_new_card_the_designs_of_every_channel(): void
    {
        $expected = [
            'classic' => ['data-image-path' => '', 'data-channels' => 'WEB'],
            'birthday' => ['data-image-path' => 'ab/cd/birthday.png', 'data-channels' => 'WEB OUTLET'],
            'christmas' => ['data-image-path' => '', 'data-channels' => 'OUTLET'],
        ];

        $view = $this->factory->create(GiftCardType::class, new GiftCard())->createView();

        self::assertSame($expected, self::designChoices($view['design']));

        // A new card that already has a channel, as GiftCardFactory::createForChannel() makes one, can still be moved
        // to another channel on the form, so it is offered the same
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);

        self::assertSame($expected, self::designChoices($this->factory->create(GiftCardType::class, $giftCard)->createView()['design']));
    }

    /**
     * The form offers designs of channels other than the one chosen, so it is the validation that holds the design
     * to the card's channel
     *
     * @test
     */
    public function it_refuses_a_design_the_chosen_channel_does_not_offer(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'design' => 'christmas']));

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors(true));
        $error = $form->get('design')->getErrors()[0] ?? null;
        self::assertNotNull($error);
        self::assertSame('setono_sylius_gift_card.gift_card.design.not_available_in_channel', $error->getMessage());
        self::assertSame(['{{ design }}' => 'Christmas', '{{ channel }}' => 'Web store'], $error->getMessageParameters());

        // The outlet offers it
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit($this->validSubmission(['code' => 'GIFTCARDCODE', 'channel' => 'OUTLET', 'currencyCode' => 'SEK', 'design' => 'christmas']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($this->christmas, $giftCard->getDesign());
    }

    /**
     * The design only decides what the card's PDF looks like, so it can be changed after issuance, to another design
     * the card's channel offers
     *
     * @test
     */
    public function it_lets_the_design_of_an_existing_card_be_changed_to_another_its_channel_offers(): void
    {
        $giftCard = $this->existingGiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->setDesign($this->classic);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        // which channels offer a design only matters while the channel can still be chosen
        self::assertSame([
            'classic' => ['data-image-path' => ''],
            'birthday' => ['data-image-path' => 'ab/cd/birthday.png'],
        ], self::designChoices($form->createView()['design']));
        self::assertSame('classic', $form->get('design')->getViewData());

        $form->submit(['design' => 'birthday']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($this->birthday, $giftCard->getDesign());
    }

    /**
     * A design that is disabled, or taken out of the channel, after a card was issued with it still prints on that
     * card. Editing the card must neither drop the design nor refuse to save it
     *
     * @test
     */
    public function it_keeps_the_design_of_an_existing_card_that_its_channel_no_longer_offers(): void
    {
        $giftCard = $this->existingGiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->setDesign($this->retired);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertSame(['classic', 'birthday', 'retired'], array_keys(self::designChoices($form->createView()['design'])));
        self::assertSame('retired', $form->get('design')->getViewData());

        $form->submit(['design' => 'retired', 'customMessage' => 'Enjoy']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($this->retired, $giftCard->getDesign());
        self::assertSame('Enjoy', $giftCard->getCustomMessage());
    }

    /**
     * A card bought in the shop takes its delivery type from the variant, which also decides whether the order ships
     * it, so changing it once the card exists would only make the card disagree with its order
     *
     * @test
     */
    public function it_shows_the_delivery_type_but_locks_it_once_the_card_exists(): void
    {
        $giftCard = $this->existingGiftCard();
        $giftCard->setDeliveryType(GiftCardDeliveryType::Physical);

        $form = $this->factory->create(GiftCardType::class, $giftCard);

        self::assertTrue($form->get('deliveryType')->isDisabled());
        self::assertSame('physical', $form->get('deliveryType')->getViewData());
        self::assertNull($form->get('deliveryType')->getConfig()->getOption('help'));

        $form->submit(['deliveryType' => 'virtual']);

        self::assertTrue($form->isSynchronized());
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
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
        // the web store is kept in kroner and also offers euros, the outlet is kept in Swedish kronor
        $this->channel = $this->channel('WEB', 'Web store', 'DKK', 'EUR');
        $outlet = $this->channel('OUTLET', 'Outlet', 'SEK');

        $frontImage = new GiftCardDesignImage();
        $frontImage->setPath('ab/cd/birthday.png');

        $this->classic = $this->design('classic', 'Classic');
        $this->birthday = $this->design('birthday', 'Birthday', $frontImage);
        $this->christmas = $this->design('christmas', 'Christmas');
        $this->retired = $this->design('retired', 'Retired');

        $designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);
        $designProvider->getDesigns($this->channel)->willReturn([$this->classic, $this->birthday]);
        $designProvider->getDesigns($outlet)->willReturn([$this->birthday, $this->christmas]);

        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository->findAll()->willReturn([$this->channel, $outlet]);

        // nobody browses in a currency in the admin
        $this->currencyProvider = new GiftCardCurrencyProvider(new CompositeCurrencyContext());

        $codeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $codeGenerator->generate()->willReturn('GENERATEDCODE');

        $type = new GiftCardType(
            GiftCard::class,
            $this->currencyProvider,
            $codeGenerator->reveal(),
            new GiftCardCodeNormalizer(),
            12,
            GiftCardDesign::class,
            $designProvider->reveal(),
            $channelRepository->reveal(),
            ['setono_sylius_gift_card'],
        );

        /** @var ObjectProphecy<RepositoryInterface<CustomerInterface>> $customerRepository */
        $customerRepository = $this->prophesize(RepositoryInterface::class);
        $this->customerRepository = $customerRepository;

        $resourceRepositoryRegistry = $this->prophesize(ServiceRegistryInterface::class);
        $resourceRepositoryRegistry->get('sylius.customer')->willReturn($this->customerRepository->reveal());

        // The customer autocomplete names its search endpoints when the form is rendered
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(Argument::cetera())->willReturn('/admin/ajax/customers');

        $preloaded = new PreloadedExtension([
            $type,
            new ChannelChoiceType($channelRepository->reveal()),
            new CustomerAutocompleteChoiceType($urlGenerator->reveal()),
            new ResourceAutocompleteChoiceType($resourceRepositoryRegistry->reveal()),
            // The design picker is an EntityType, but the choices are handed to it explicitly, so only the identifier
            // metadata is ever read from Doctrine
            new EntityType($this->createManagerRegistry()),
        ], []);

        return [$preloaded, new ValidatorExtension($this->createValidator($designProvider->reveal()))];
    }

    private function createValidator(GiftCardDesignProviderInterface $designProvider): ValidatorInterface
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
                GiftCardDesignIsAvailableInChannelValidator::class => new GiftCardDesignIsAvailableInChannelValidator($designProvider),
                GiftCardCurrencyIsOfferedByChannelValidator::class => new GiftCardCurrencyIsOfferedByChannelValidator($this->currencyProvider),
            ]))
            ->getValidator()
        ;
    }

    private function createManagerRegistry(): ManagerRegistry
    {
        $classMetadata = $this->prophesize(ClassMetadata::class);
        $classMetadata->getIdentifierFieldNames()->willReturn(['id']);
        $classMetadata->getTypeOfField('id')->willReturn('integer');
        $classMetadata->hasAssociation('id')->willReturn(false);

        $manager = $this->prophesize(ObjectManager::class);
        $manager->getClassMetadata(GiftCardDesign::class)->willReturn($classMetadata->reveal());

        $registry = $this->prophesize(ManagerRegistry::class);
        $registry->getManagerForClass(GiftCardDesign::class)->willReturn($manager->reveal());

        return $registry->reveal();
    }

    /**
     * @return array<string, array<array-key, mixed>> the attributes of each design the picker offers, by its value
     */
    private static function designChoices(FormView $design): array
    {
        $vars = $design->vars;
        self::assertIsArray($vars);
        self::assertIsArray($vars['choices']);

        $choices = [];
        foreach ($vars['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->value);
            self::assertIsArray($choice->attr);

            $choices[$choice->value] = $choice->attr;
        }

        return $choices;
    }

    /**
     * The currency the form's currency field starts out at for the card
     */
    private function currencyValue(GiftCard $giftCard): mixed
    {
        $vars = $this->factory->create(GiftCardType::class, $giftCard)->createView()->children['currencyCode']->vars;
        self::assertIsArray($vars);

        return $vars['value'] ?? null;
    }

    /**
     * @return array<string, array<array-key, mixed>> the attributes of each currency the field offers, by its value
     */
    private static function choiceAttributes(FormView $field): array
    {
        $vars = $field->vars;
        self::assertIsArray($vars);
        self::assertIsArray($vars['choices']);

        $choices = [];
        foreach ($vars['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->value);
            self::assertIsArray($choice->attr);

            $choices[$choice->value] = $choice->attr;
        }

        return $choices;
    }

    private function channel(string $code, string $name, string $baseCurrencyCode = 'DKK', string ...$currencyCodes): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);
        $channel->setBaseCurrency($this->currency($baseCurrencyCode));
        foreach ($currencyCodes as $currencyCode) {
            $channel->addCurrency($this->currency($currencyCode));
        }

        return $channel;
    }

    private function design(string $code, string $name, ?GiftCardDesignImageInterface $frontImage = null): GiftCardDesignInterface
    {
        $design = $this->prophesize(GiftCardDesignInterface::class);
        $design->getCode()->willReturn($code);
        $design->getName()->willReturn($name);
        $design->getFrontImage()->willReturn($frontImage);

        return $design->reveal();
    }

    private function currency(string $code): CurrencyInterface
    {
        $currency = new Currency();
        $currency->setCode($code);

        return $currency;
    }
}
