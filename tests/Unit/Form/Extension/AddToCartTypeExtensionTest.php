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
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCartItemAvailabilityValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignRequiredValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardFitsCartValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmountValidator;
use Sylius\Bundle\CoreBundle\Form\Extension\CartItemTypeExtension;
use Sylius\Bundle\CoreBundle\Form\Type\Order\AddToCartType;
use Sylius\Bundle\CoreBundle\Validator\Constraints\CartItemAvailabilityValidator;
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
use Sylius\Component\Inventory\Checker\AvailabilityChecker;
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
     * The cart's totals are integer columns. A gift card that would take them past what they hold is refused on the
     * form itself, before the handler turns it into cards
     *
     * @test
     */
    public function it_does_not_hand_a_gift_card_the_cart_has_no_room_for_to_the_cart_handler(): void
    {
        $this->cartGiftCardHandler->handle(Argument::any())->shouldNotBeCalled();

        $cart = new Order();
        $existing = new OrderItem();
        $existing->setUnitPrice(2147483647);
        new OrderItemUnit($existing);
        $cart->addItem($existing);

        $form = $this->createForm($this->command($this->product(giftCard: true), $cart));
        $form->submit([
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => '1.00'],
        ]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors(), 'the cart is what has no room, so the error is the form\'s own');
        self::assertCount(0, $form->get('giftCardInformation')->get('amount')->getErrors());
    }

    /**
     * A request that leaves out the variant choice of a product with several variants leaves the line without a
     * variant, which Sylius' stock check, mapped on the command as well, read regardless and ended the request in a
     * 500. The form refuses it on the variant field instead, once, and the handler never sees it
     *
     * @test
     */
    public function it_does_not_hand_a_submission_without_a_variant_to_the_cart_handler(): void
    {
        $this->cartGiftCardHandler->handle(Argument::any())->shouldNotBeCalled();

        $product = $this->product(giftCard: true);
        $command = $this->command($product);
        $virtual = $command->getCartItem()->getVariant();
        self::assertNotNull($virtual);
        $physical = new ProductVariant();
        $product->addVariant($physical);

        // the choice tells the variants apart by their codes, and labels them by their names
        foreach (['VIRTUAL' => $virtual, 'PHYSICAL' => $physical] as $code => $variant) {
            $variant->setCode($code);
            $variant->setCurrentLocale('en_US');
            $variant->setName(ucfirst(strtolower($code)));
        }

        $form = $this->createForm($command);
        self::assertTrue($form->get('cartItem')->has('variant'), 'the form should ask which of the two variants to add');

        $form->submit([
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => '50.00'],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors(true));
        self::assertCount(1, $form->get('cartItem')->get('variant')->getErrors());
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
     * Without a command there is nothing to hold the gift card information, so it is not asked for
     *
     * @test
     */
    public function it_builds_the_form_without_a_command(): void
    {
        $form = $this->factory->create(AddToCartType::class, null, ['product' => $this->product(giftCard: true)]);

        self::assertFalse($form->has('giftCardInformation'));
    }

    /**
     * Sylius makes the line with the product's first enabled variant, so the line of a product none of whose variants
     * is enabled has none. The extension read the product off it, which ended every add to cart of such a product in a
     * 500 while the form was built (#480). It goes by the product the form is given instead
     *
     * @test
     */
    public function it_asks_for_the_gift_card_information_on_a_gift_card_product_whose_line_has_no_variant(): void
    {
        $product = $this->productWithVariants(giftCard: true);

        $form = $this->createForm($this->commandWithoutVariant(), $product);

        self::assertTrue($form->has('giftCardInformation'));
    }

    /** @test */
    public function it_leaves_the_form_of_any_other_product_whose_line_has_no_variant_alone(): void
    {
        $product = $this->productWithVariants(giftCard: false);

        $form = $this->createForm($this->commandWithoutVariant(), $product);

        self::assertFalse($form->has('giftCardInformation'));
    }

    /**
     * A page opened before the admin disabled the product's variants still sends the variant it had chosen, which
     * Sylius' variant choice accepts, so the line gets its variant only once the form is submitted. The gift card
     * information was asked for and validated all the same, so the submission is handed on like any other
     *
     * @test
     */
    public function it_hands_a_submission_that_chooses_the_variant_the_line_lacked_to_the_cart_handler(): void
    {
        $command = $this->commandWithoutVariant();

        $this->cartGiftCardHandler->handle($command)->shouldBeCalledOnce();

        $form = $this->createForm($command, $this->productWithVariants(giftCard: true));
        $form->submit([
            'cartItem' => ['quantity' => '1', 'variant' => 'PHYSICAL'],
            'giftCardInformation' => ['amount' => '50.00'],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('PHYSICAL', $command->getCartItem()->getVariant()?->getCode());
        self::assertSame(5000, $command->getGiftCardInformation()->getAmount());
    }

    /** @test */
    public function it_extends_the_add_to_cart_form(): void
    {
        self::assertSame([AddToCartType::class], [...AddToCartTypeExtension::getExtendedTypes()]);
    }

    /**
     * @param ProductInterface|null $product the product the add to cart route looks up, by default the line's
     *
     * @return FormInterface<AddToCartCommand>
     */
    private function createForm(AddToCartCommand $command, ?ProductInterface $product = null): FormInterface
    {
        $product ??= $command->getCartItem()->getProduct();
        self::assertInstanceOf(ProductInterface::class, $product);

        /** @var FormInterface<AddToCartCommand> $form */
        $form = $this->factory->create(AddToCartType::class, $command, ['product' => $product]);

        return $form;
    }

    /**
     * What the decorated command factory hands the form on the product page: a new line holding one unit of the
     * product's only variant, and gift card information seeded with the line's unit price
     */
    private function command(ProductInterface $product, ?Order $cart = null): AddToCartCommand
    {
        $variant = new ProductVariant();
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        new OrderItemUnit($item);

        return new AddToCartCommand($cart ?? new Order(), $item, new GiftCardInformation($item->getUnitPrice()));
    }

    /**
     * What the decorated command factory hands the form for a product none of whose variants is enabled: a line
     * without a variant, and gift card information without an amount to suggest
     */
    private function commandWithoutVariant(): AddToCartCommand
    {
        $item = new OrderItem();
        new OrderItemUnit($item);

        return new AddToCartCommand(new Order(), $item, new GiftCardInformation(null));
    }

    /**
     * A product with two variants, so the form asks which one to add. The choice tells them apart by their codes, and
     * labels them by their names
     */
    private function productWithVariants(bool $giftCard): Product
    {
        $product = $this->product($giftCard);

        foreach (['VIRTUAL', 'PHYSICAL'] as $code) {
            $variant = new ProductVariant();
            $variant->setCode($code);
            $variant->setCurrentLocale('en_US');
            $variant->setName(ucfirst(strtolower($code)));
            $product->addVariant($variant);
        }

        return $product;
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
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/AddToCartCommandInterface.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                // Sylius' stock check, which the mapping carries too, under the alias its constraint names. The
                // variants here are not tracked, so it passes them whatever the quantity
                'sylius_cart_item_availability' => new CartItemAvailabilityValidator(new AvailabilityChecker()),
                GiftCardFitsCartValidator::class => new GiftCardFitsCartValidator(
                    2147483647,
                    $moneyFormatter->reveal(),
                    $localeContext->reveal(),
                ),
                ValidGiftCardAmountValidator::class => new ValidGiftCardAmountValidator(
                    $channelContext->reveal(),
                    $amountLimitsProvider->reveal(),
                    $moneyFormatter->reveal(),
                    $localeContext->reveal(),
                ),
                GiftCardMessageLengthValidator::class => new GiftCardMessageLengthValidator(200),
                GiftCardCartItemAvailabilityValidator::class => new GiftCardCartItemAvailabilityValidator(new AvailabilityChecker()),
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
