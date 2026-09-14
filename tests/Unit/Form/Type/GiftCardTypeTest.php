<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Form\Type\CustomerAutocompleteChoiceType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
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
