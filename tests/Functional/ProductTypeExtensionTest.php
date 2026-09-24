<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * A product becomes a gift card product through the checkbox the plugin adds to Sylius' admin product form. The
 * Playwright specs cover that the checkbox is rendered and survives a save; this covers the form the application
 * builds, without a browser
 */
final class ProductTypeExtensionTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_adds_an_optional_gift_card_checkbox_to_the_product_form(): void
    {
        $field = $this->createForm(new Product())->get('giftCard');

        self::assertInstanceOf(CheckboxType::class, $field->getConfig()->getType()->getInnerType());
        self::assertFalse($field->isRequired(), 'most products are not gift cards');
        self::assertSame('setono_sylius_gift_card.form.product.gift_card', $field->getConfig()->getOption('label'));
    }

    /** @test */
    public function it_shows_whether_the_product_is_a_gift_card(): void
    {
        $giftCard = new Product();
        $giftCard->setGiftCard(true);

        self::assertTrue($this->createForm($giftCard)->get('giftCard')->getData());
        self::assertFalse($this->createForm(new Product())->get('giftCard')->getData());
    }

    /** @test */
    public function it_flags_and_unflags_the_product(): void
    {
        $product = new Product();

        $this->createForm($product)->submit(['giftCard' => '1'], false);
        self::assertTrue($product->isGiftCard());

        // a browser leaves an unticked checkbox out of the request, which Symfony submits as null
        $this->createForm($product)->submit(['giftCard' => null], false);
        self::assertFalse($product->isGiftCard());
    }

    /**
     * @return FormInterface<Product>
     */
    private function createForm(Product $product): FormInterface
    {
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        /** @var FormInterface<Product> $form */
        $form = $formFactory->create(ProductType::class, $product, ['csrf_protection' => false]);

        return $form;
    }
}
