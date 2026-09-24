<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardInformationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimits;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignRequiredValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmountValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

final class GiftCardInformationTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    private GiftCardDesignInterface $design;

    /** A second design the channel offers, the one with front artwork */
    private GiftCardDesignInterface $illustratedDesign;

    /**
     * What the amount limits provider answers as the maximum; the range is only quoted when one is configured
     */
    private ?int $maximumAmount = null;

    private ?string $baseCurrencyCode = 'USD';

    /** @test */
    public function it_maps_a_valid_submission(): void
    {
        $information = $this->createInformation();

        $form = $this->factory->create(GiftCardInformationType::class, $information);
        $form->submit([
            'amount' => '50.00',
            'customMessage' => 'Happy birthday',
            'design' => 'classic',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        // Sylius money fields are minor units on the model side
        self::assertSame(5000, $information->getAmount());
        self::assertSame('Happy birthday', $information->getCustomMessage());
        self::assertSame($this->design, $information->getDesign());
    }

    /**
     * The data mapper writes the submitted amount into the information object during submit(), long before the
     * POST_SUBMIT validation listener runs, so a non-nullable setter turns a blank amount into a 500
     *
     * @test
     */
    public function it_reports_a_blank_amount_as_a_validation_error(): void
    {
        $information = $this->createInformation();

        $form = $this->factory->create(GiftCardInformationType::class, $information);
        $form->submit([
            'amount' => '',
            'customMessage' => '',
            'design' => 'classic',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertNull($information->getAmount());
        self::assertGreaterThan(0, $form->get('amount')->getErrors()->count());
    }

    /**
     * The customer should learn the purchasable range from the field, not from the error after submitting, and the
     * money in it is formatted in the locale being browsed
     *
     * @test
     */
    public function it_quotes_the_amount_limits_and_asks_for_a_numeric_keypad(): void
    {
        $form = $this->factory->create(GiftCardInformationType::class, $this->createInformation());
        $amount = $form->get('amount')->getConfig();

        self::assertSame('setono_sylius_gift_card.form.gift_card_information.amount_help_minimum', $amount->getOption('help'));
        self::assertSame(['%minimum%' => '1,00 $US'], $amount->getOption('help_translation_parameters'));

        $attr = $amount->getOption('attr');
        self::assertIsArray($attr);
        self::assertSame('decimal', $attr['inputmode'] ?? null);
        self::assertArrayHasKey('data-js-gc-amount-input', $attr);
    }

    /**
     * With a maximum configured both ends of the range are quoted, each formatted in the locale being browsed
     *
     * @test
     */
    public function it_quotes_the_range_when_a_maximum_is_configured(): void
    {
        $this->maximumAmount = 250000;
        $this->rebuildFormFactory();

        $amount = $this->factory->create(GiftCardInformationType::class, $this->createInformation())->get('amount')->getConfig();

        self::assertSame('setono_sylius_gift_card.form.gift_card_information.amount_help_range', $amount->getOption('help'));
        self::assertSame(
            ['%minimum%' => '1,00 $US', '%maximum%' => '2 500,00 $US'],
            $amount->getOption('help_translation_parameters'),
        );
    }

    /**
     * Without a base currency there is nothing to format the limits as, so the field carries no help rather than
     * money in a currency the shop does not sell in
     *
     * @test
     */
    public function it_quotes_no_limits_when_the_channel_has_no_base_currency(): void
    {
        $this->baseCurrencyCode = null;
        $this->rebuildFormFactory();

        $amount = $this->factory->create(GiftCardInformationType::class, $this->createInformation())->get('amount')->getConfig();

        self::assertNull($amount->getOption('help'));
        self::assertSame([], $amount->getOption('help_translation_parameters'));
    }

    /**
     * The design is required whenever the channel offers any, so the picker starts on the first of them rather
     * than confronting the customer with an error for a choice they may not care about
     *
     * @test
     */
    public function it_offers_the_channel_designs_and_preselects_the_first(): void
    {
        $design = $this->factory->create(GiftCardInformationType::class, $this->createInformation())->get('design');

        self::assertSame([$this->design, $this->illustratedDesign], $design->getConfig()->getOption('choices'));
        self::assertSame($this->design, $design->getData());
    }

    /**
     * The live preview swaps the card artwork as the customer picks a design, reading it from the choice. A design
     * without front artwork previews the framed default, which an empty path tells the script
     *
     * @test
     */
    public function it_hands_the_live_preview_each_designs_front_image(): void
    {
        $view = $this->factory->create(GiftCardInformationType::class, $this->createInformation())->createView();

        $vars = $view->children['design']->vars;
        self::assertIsArray($vars);
        self::assertIsArray($vars['choices']);

        $paths = [];
        foreach ($vars['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->value);
            self::assertIsArray($choice->attr);

            $paths[$choice->value] = $choice->attr['data-image-path'] ?? null;
        }

        self::assertSame(['classic' => '', 'birthday' => 'ab/cd/birthday.png'], $paths);
    }

    /**
     * The textarea stops the customer at the configured length and says so up front; the validator enforces the
     * same limit for a request that ignores the attribute
     *
     * @test
     */
    public function it_limits_the_message_to_the_configured_length(): void
    {
        $form = $this->factory->create(GiftCardInformationType::class, $this->createInformation());
        $message = $form->get('customMessage')->getConfig();

        self::assertSame(['%limit%' => 200], $message->getOption('help_translation_parameters'));
        $attr = $message->getOption('attr');
        self::assertIsArray($attr);
        self::assertSame(200, $attr['maxlength'] ?? null);

        $form->submit([
            'amount' => '50.00',
            'customMessage' => str_repeat('a', 201),
            'design' => 'classic',
        ]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('customMessage')->getErrors());
    }

    /**
     * The form factory is built in setUp() from what getExtensions() is told, so a test that changes that has to
     * build it again. Everything this test registers comes from getExtensions()
     */
    private function rebuildFormFactory(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->getFormFactory();
    }

    /**
     * Mirrors GiftCardInformationFactory, which seeds the information object with the order item's unit price
     */
    private function createInformation(): GiftCardInformation
    {
        return new GiftCardInformation(2500);
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $design = $this->prophesize(GiftCardDesignInterface::class);
        $design->getCode()->willReturn('classic');
        $design->getName()->willReturn('Classic');
        $design->getFrontImage()->willReturn(null);
        $this->design = $design->reveal();

        $frontImage = new GiftCardDesignImage();
        $frontImage->setPath('ab/cd/birthday.png');

        $illustratedDesign = $this->prophesize(GiftCardDesignInterface::class);
        $illustratedDesign->getCode()->willReturn('birthday');
        $illustratedDesign->getName()->willReturn('Birthday');
        $illustratedDesign->getFrontImage()->willReturn($frontImage);
        $this->illustratedDesign = $illustratedDesign->reveal();

        $channel = $this->prophesize(ChannelInterface::class);
        if (null === $this->baseCurrencyCode) {
            $channel->getBaseCurrency()->willReturn(null);
        } else {
            $currency = $this->prophesize(CurrencyInterface::class);
            $currency->getCode()->willReturn($this->baseCurrencyCode);
            $channel->getBaseCurrency()->willReturn($currency->reveal());
        }

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel->reveal());

        $designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);
        $designProvider->getDesigns($channel->reveal())->willReturn([$this->design, $this->illustratedDesign]);

        $amountLimitsProvider = $this->prophesize(GiftCardAmountLimitsProviderInterface::class);
        $amountLimitsProvider->getLimits($channel->reveal())->willReturn(new GiftCardAmountLimits(100, $this->maximumAmount));

        $moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $moneyFormatter->format(100, 'USD', 'fr_FR')->willReturn('1,00 $US');
        $moneyFormatter->format(250000, 'USD', 'fr_FR')->willReturn('2 500,00 $US');

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('fr_FR');

        $type = new GiftCardInformationType(
            GiftCardInformation::class,
            GiftCardDesign::class,
            $channelContext->reveal(),
            $designProvider->reveal(),
            200,
            $amountLimitsProvider->reveal(),
            $moneyFormatter->reveal(),
            $localeContext->reveal(),
        );

        // The design picker is an EntityType, but the choices are handed to it explicitly, so only the
        // identifier metadata is ever read from Doctrine
        $entityType = new EntityType($this->createManagerRegistry());

        $amountValidator = new ValidGiftCardAmountValidator(
            $channelContext->reveal(),
            $amountLimitsProvider->reveal(),
            $this->prophesize(MoneyFormatterInterface::class)->reveal(),
        );

        // The rules live in the validation mapping rather than on the form, so the mapping is what is loaded here
        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCardInformation.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ValidGiftCardAmountValidator::class => $amountValidator,
                GiftCardMessageLengthValidator::class => new GiftCardMessageLengthValidator(200),
                GiftCardDesignRequiredValidator::class => new GiftCardDesignRequiredValidator(
                    $channelContext->reveal(),
                    $designProvider->reveal(),
                ),
            ]))
            ->getValidator()
        ;

        return [
            new PreloadedExtension([$type, $entityType], []),
            new ValidatorExtension($validator),
        ];
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
}
