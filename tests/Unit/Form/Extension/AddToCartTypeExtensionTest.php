<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Extension;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Cart\CartGiftCardHandlerInterface;
use Setono\SyliusGiftCardPlugin\Form\Extension\AddToCartTypeExtension;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardInformationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimits;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignRequiredValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmountValidator;
use Sylius\Bundle\CoreBundle\Form\Extension\CartItemTypeExtension;
use Sylius\Bundle\CoreBundle\Form\Type\Order\AddToCartType;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommand as BaseAddToCartCommand;
use Sylius\Bundle\OrderBundle\Form\DataMapper\OrderItemQuantityDataMapper;
use Sylius\Bundle\OrderBundle\Form\Type\CartItemType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product as PlainProduct;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Order\Factory\OrderItemUnitFactory;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * Sylius' add to cart form knows nothing about gift cards. The extension asks for the gift card information on a
 * gift card product only, and hands a submission to the cart handler once it has been validated, so an amount the
 * shop does not sell never turns into a card
 */
final class AddToCartTypeExtensionTest extends TypeTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CartGiftCardHandlerInterface> */
    private ObjectProphecy $cartGiftCardHandler;

    /** @test */
    public function it_asks_for_the_gift_card_information_on_a_gift_card_product(): void
    {
        $form = $this->createForm($this->command($this->product(giftCard: true)));

        self::assertTrue($form->has('giftCardInformation'));
        self::assertInstanceOf(
            GiftCardInformationType::class,
            $form->get('giftCardInformation')->getConfig()->getType()->getInnerType(),
        );
    }

    /**
     * @test
     *
     * @dataProvider productsThatAreNotGiftCards
     */
    public function it_leaves_the_form_of_any_other_product_alone(ProductInterface $product): void
    {
        $this->cartGiftCardHandler->handle(Argument::any())->shouldNotBeCalled();

        $form = $this->createForm($this->command($product));
        self::assertFalse($form->has('giftCardInformation'));

        $form->submit(['cartItem' => ['quantity' => '1']]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    /**
     * @return iterable<string, array{ProductInterface}>
     */
    public static function productsThatAreNotGiftCards(): iterable
    {
        $product = new Product();
        $product->setGiftCard(false);

        yield 'a product that is not a gift card' => [$product];

        // an application whose product class does not implement the plugin's product interface
        yield 'a product without the gift card flag' => [new PlainProduct()];
    }

    /** @test */
    public function it_hands_a_valid_submission_to_the_cart_handler(): void
    {
        $command = $this->command($this->product(giftCard: true));

        $this->cartGiftCardHandler->handle($command)->shouldBeCalledOnce();

        $form = $this->createForm($command);
        $form->submit([
            'cartItem' => ['quantity' => '2'],
            'giftCardInformation' => ['amount' => '50.00', 'customMessage' => 'Happy birthday'],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        // what the handler receives: the chosen information, and a unit for every card it is to issue
        self::assertSame(5000, $command->getGiftCardInformation()->getAmount());
        self::assertSame('Happy birthday', $command->getGiftCardInformation()->getCustomMessage());
        self::assertCount(2, $command->getCartItem()->getUnits());
    }

    /**
     * The handler listens after the validation listener. Were it to listen before, the form would not know about
     * the violations yet and a blank amount, or one outside the limits, would already have been turned into cards
     *
     * @test
     *
     * @dataProvider invalidAmounts
     */
    public function it_does_not_hand_an_invalid_submission_to_the_cart_handler(string $amount): void
    {
        $this->cartGiftCardHandler->handle(Argument::any())->shouldNotBeCalled();

        $form = $this->createForm($this->command($this->product(giftCard: true)));
        $form->submit([
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => $amount],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('giftCardInformation')->get('amount')->getErrors());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'a blank amount' => [''];
        yield 'an amount below the minimum' => ['0.50'];
    }

    /**
     * Sylius binds the form to its own command class. The decorated command factory hands the form the plugin's
     * command instead, which the form would reject as the wrong type unless it is told to expect it
     *
     * @test
     */
    public function it_binds_the_form_to_the_gift_card_aware_command(): void
    {
        $form = $this->createForm($this->command($this->product(giftCard: false)));

        self::assertSame(AddToCartCommand::class, $form->getConfig()->getDataClass());
    }

    /**
     * Without a command there is no line to tell the product by, so there is nothing to ask for either
     *
     * @test
     */
    public function it_builds_the_form_without_a_command(): void
    {
        $form = $this->factory->create(AddToCartType::class, null, ['product' => $this->product(giftCard: true)]);

        self::assertFalse($form->has('giftCardInformation'));
    }

    /** @test */
    public function it_extends_the_add_to_cart_form(): void
    {
        self::assertSame([AddToCartType::class], [...AddToCartTypeExtension::getExtendedTypes()]);
    }

    /**
     * @return FormInterface<AddToCartCommand>
     */
    private function createForm(AddToCartCommand $command): FormInterface
    {
        $product = $command->getCartItem()->getProduct();
        self::assertInstanceOf(ProductInterface::class, $product);

        /** @var FormInterface<AddToCartCommand> $form */
        $form = $this->factory->create(AddToCartType::class, $command, ['product' => $product]);

        return $form;
    }

    /**
     * What the decorated command factory hands the form on the product page: a new line holding one unit of the
     * product's only variant, and gift card information seeded with the line's unit price
     */
    private function command(ProductInterface $product): AddToCartCommand
    {
        $variant = new ProductVariant();
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        new OrderItemUnit($item);

        return new AddToCartCommand(new Order(), $item, new GiftCardInformation($item->getUnitPrice()));
    }

    private function product(bool $giftCard): Product
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        return $product;
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $this->cartGiftCardHandler = $this->prophesize(CartGiftCardHandlerInterface::class);

        $currency = new Currency();
        $currency->setCode('USD');

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getBaseCurrency()->willReturn($currency);

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel->reveal());

        // a channel without designs, so the design is not asked for and this test is only about the extension
        $designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);
        $designProvider->getDesigns($channel->reveal())->willReturn([]);

        $amountLimitsProvider = $this->prophesize(GiftCardAmountLimitsProviderInterface::class);
        $amountLimitsProvider->getLimits($channel->reveal())->willReturn(new GiftCardAmountLimits(100, null));

        $moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $moneyFormatter->format(Argument::cetera())->willReturn('$1.00');

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $types = [
            // Sylius binds the form to its own command class
            new AddToCartType(BaseAddToCartCommand::class, ['sylius']),
            new CartItemType(OrderItem::class, ['sylius'], new OrderItemQuantityDataMapper(
                new OrderItemQuantityModifier(new OrderItemUnitFactory(OrderItemUnit::class)),
                new DataMapper(),
            )),
            new GiftCardInformationType(
                GiftCardInformation::class,
                GiftCardDesign::class,
                $channelContext->reveal(),
                $designProvider->reveal(),
                200,
                $amountLimitsProvider->reveal(),
                $moneyFormatter->reveal(),
                $localeContext->reveal(),
            ),
            new EntityType($this->createManagerRegistry()),
        ];

        $typeExtensions = [
            AddToCartType::class => [new AddToCartTypeExtension($this->cartGiftCardHandler->reveal(), AddToCartCommand::class)],
            CartItemType::class => [new CartItemTypeExtension()],
        ];

        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCardInformation.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ValidGiftCardAmountValidator::class => new ValidGiftCardAmountValidator(
                    $channelContext->reveal(),
                    $amountLimitsProvider->reveal(),
                    $moneyFormatter->reveal(),
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
            new PreloadedExtension($types, $typeExtensions),
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
