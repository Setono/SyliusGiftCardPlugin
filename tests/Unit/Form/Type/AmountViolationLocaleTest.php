<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardInformationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProvider;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignRequiredValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmountValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatter;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * The amount field quotes the purchasable range twice: in its help text before the customer submits, and in the
 * error after a too low or too high amount. Both are the same limit, formatted by the same Sylius money formatter,
 * so a shopper browsing in French or Danish must read it the same way in both places
 */
final class AmountViolationLocaleTest extends TypeTestCase
{
    use ProphecyTrait;

    private string $localeCode = 'fr_FR';

    private string $currencyCode = 'USD';

    /**
     * @test
     *
     * @dataProvider shoppers
     */
    public function the_error_quotes_the_minimum_the_way_the_help_text_does(string $localeCode, string $currencyCode): void
    {
        $this->browse($localeCode, $currencyCode);

        $amount = $this->submitAmount('0.50');

        self::assertSame($this->helpParameter($amount, '%minimum%'), $this->errorParameter($amount, '{{ minimum }}'));
    }

    /**
     * @test
     *
     * @dataProvider shoppers
     */
    public function the_error_quotes_the_maximum_the_way_the_help_text_does(string $localeCode, string $currencyCode): void
    {
        $this->browse($localeCode, $currencyCode);

        $amount = $this->submitAmount('3000');

        self::assertSame($this->helpParameter($amount, '%maximum%'), $this->errorParameter($amount, '{{ maximum }}'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shoppers(): iterable
    {
        yield 'a French shopper on a USD channel' => ['fr_FR', 'USD'];
        yield 'a Danish shopper in a shop selling in DKK' => ['da_DK', 'DKK'];
    }

    /**
     * The form factory is built in setUp() from what getExtensions() is told, so it is built again for the
     * shopper the test is about
     */
    private function browse(string $localeCode, string $currencyCode): void
    {
        $this->localeCode = $localeCode;
        $this->currencyCode = $currencyCode;

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->getFormFactory();
    }

    /**
     * @return FormInterface<mixed> the amount field, after the whole gift card information form was submitted
     */
    private function submitAmount(string $amount): FormInterface
    {
        $form = $this->factory->create(GiftCardInformationType::class, new GiftCardInformation(0));
        $form->submit(['amount' => $amount]);

        return $form->get('amount');
    }

    /**
     * @param FormInterface<mixed> $amount
     */
    private function helpParameter(FormInterface $amount, string $parameter): mixed
    {
        $help = $amount->getConfig()->getOption('help_translation_parameters');
        self::assertIsArray($help);
        self::assertArrayHasKey($parameter, $help);

        return $help[$parameter];
    }

    /**
     * @param FormInterface<mixed> $amount
     */
    private function errorParameter(FormInterface $amount, string $parameter): mixed
    {
        $errors = $amount->getErrors();
        self::assertCount(1, $errors);

        $error = $errors[0];
        self::assertInstanceOf(FormError::class, $error);

        $parameters = $error->getMessageParameters();
        self::assertArrayHasKey($parameter, $parameters);

        return $parameters[$parameter];
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $currency = new Currency();
        $currency->setCode($this->currencyCode);
        $channel = new Channel();
        $channel->setBaseCurrency($currency);

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn($this->localeCode);

        $designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);
        $designProvider->getDesigns($channel)->willReturn([]);

        // The services as the plugin wires them: the default minimum with a maximum configured, so both ends of
        // the range are quoted, and Sylius' own money formatter
        $limits = new GiftCardAmountLimitsProvider(100, 250000);
        $moneyFormatter = new MoneyFormatter();

        $type = new GiftCardInformationType(
            GiftCardInformation::class,
            GiftCardDesign::class,
            $channelContext->reveal(),
            $designProvider->reveal(),
            200,
            $limits,
            $moneyFormatter,
            $localeContext->reveal(),
        );

        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCardInformation.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ValidGiftCardAmountValidator::class => new ValidGiftCardAmountValidator(
                    $channelContext->reveal(),
                    $limits,
                    $moneyFormatter,
                    $localeContext->reveal(),
                ),
                GiftCardMessageLengthValidator::class => new GiftCardMessageLengthValidator(200),
                GiftCardDesignRequiredValidator::class => new GiftCardDesignRequiredValidator(
                    $channelContext->reveal(),
                    $designProvider->reveal(),
                ),
            ]))
            ->getValidator()
        ;

        return [
            new PreloadedExtension([$type, new EntityType($this->createManagerRegistry())], []),
            new ValidatorExtension($validator),
        ];
    }

    /**
     * The design picker is an EntityType, and even without choices it reads the identifier metadata from Doctrine
     */
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
