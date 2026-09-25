<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "create gift card product" button on the gift cards index scaffolds a product the merchant only has to review.
 * This presses it the way the admin does: with the CSRF token the grid action renders, into the real product
 * factory and database
 */
final class CreateGiftCardProductActionTest extends AdminFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel();
        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_scaffolds_a_disabled_gift_card_product_with_a_variant_per_delivery_type(): void
    {
        $response = $this->pressCreateGiftCardProduct();

        $product = $this->findProduct('gift_card');
        self::assertTrue($response->isRedirect(sprintf('/admin/products/%d/edit', (int) $product->getId())));
        self::assertStringContainsString(
            'A gift card product was created. Review and enable it below.',
            (string) $this->followRedirect($response)->getContent(),
        );

        // Disabled until the merchant has reviewed it
        self::assertFalse($product->isEnabled());
        self::assertTrue($product->isGiftCard());
        self::assertTrue($product->hasChannel($this->getChannel()));

        $variants = [];
        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            $variants[(string) $variant->getCode()] = $variant;
        }
        ksort($variants);
        self::assertSame(['gift_card_physical', 'gift_card_virtual'], array_keys($variants));

        // The delivery type of the cards bought through a variant is derived from whether it is shipped
        self::assertTrue($variants['gift_card_physical']->isShippingRequired());
        self::assertFalse($variants['gift_card_virtual']->isShippingRequired());

        foreach ($variants as $variant) {
            self::assertSame(5000, $variant->getChannelPricingForChannel($this->getChannel())?->getPrice());
        }
    }

    /**
     * Every press creates another product, so a second one needs a code of its own. The delivery option is shared by
     * all gift card products rather than duplicated
     *
     * @test
     */
    public function it_gives_another_gift_card_product_the_next_free_code_and_shares_the_delivery_option(): void
    {
        $this->pressCreateGiftCardProduct();
        $this->pressCreateGiftCardProduct();

        $first = $this->findProduct('gift_card');
        $second = $this->findProduct('gift_card_2');

        self::assertSame(['gift_card_delivery'], $this->optionCodes($first));
        self::assertSame(['gift_card_delivery'], $this->optionCodes($second));

        /** @var RepositoryInterface<ProductOptionInterface> $optionRepository */
        $optionRepository = self::getContainer()->get('sylius.repository.product_option');
        self::assertCount(1, $optionRepository->findBy(['code' => 'gift_card_delivery']));
    }

    /**
     * A merchant who created a "Gift card" product by hand before installing the plugin already has the slug
     * "gift-card", which Sylius' admin derives from that name. The scaffolded product's slug is derived from its code
     * and must be unique per locale, so the code "gift_card" is not free either
     *
     * @test
     */
    public function it_scaffolds_a_gift_card_product_in_a_shop_that_already_sells_a_product_with_the_slug_gift_card(): void
    {
        $this->persistHandmadeProduct(['en_US' => 'gift-card']);

        $response = $this->pressCreateGiftCardProduct();
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect to the new product, got a %d response', $response->getStatusCode()));

        // The code and the slug stay a pair: the next free code is the one whose slug is free as well
        $product = $this->findProduct('gift_card_2');
        self::assertTrue($response->isRedirect(sprintf('/admin/products/%d/edit', (int) $product->getId())));
        self::assertSame('gift-card-2', $product->getTranslation('en_US')->getSlug());
        $this->assertNoProduct('gift_card');
    }

    /**
     * The scaffolded product gets a translation in every locale of the shop, so a slug taken in any of them rules the
     * code out, not only one taken in the default locale
     *
     * @test
     */
    public function it_skips_a_code_whose_slug_a_product_uses_in_another_locale_than_the_default_one(): void
    {
        $danish = new Locale();
        $danish->setCode('da_DK');
        $this->manager->persist($danish);
        $this->getChannel()->addLocale($danish);
        $this->manager->flush();

        $this->persistHandmadeProduct(['da_DK' => 'gift-card']);

        $response = $this->pressCreateGiftCardProduct();
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect to the new product, got a %d response', $response->getStatusCode()));

        // The code and the slug stay a pair: the next free code is the one whose slug is free as well
        $product = $this->findProduct('gift_card_2');
        self::assertTrue($response->isRedirect(sprintf('/admin/products/%d/edit', (int) $product->getId())));
        self::assertSame('gift-card-2', $product->getTranslation('en_US')->getSlug());
        self::assertSame('gift-card-2', $product->getTranslation('da_DK')->getSlug());
        $this->assertNoProduct('gift_card');
    }

    /** @test */
    public function it_creates_nothing_without_the_token_of_the_grid_action(): void
    {
        $response = $this->request('POST', '/admin/gift-cards/create-product', ['_csrf_token' => 'forged']);

        self::assertSame(403, $response->getStatusCode());

        /** @var RepositoryInterface<ProductInterface> $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');
        self::assertSame([], $productRepository->findAll());
    }

    private function pressCreateGiftCardProduct(): Response
    {
        $index = $this->request('GET', '/admin/gift-cards/');
        $token = self::valueOf($index, '//form[@action="/admin/gift-cards/create-product"]//input[@name="_csrf_token"]');

        return $this->request('POST', '/admin/gift-cards/create-product', ['_csrf_token' => $token]);
    }

    private function findProduct(string $code): ProductInterface
    {
        $this->manager->clear();

        /** @var RepositoryInterface<ProductInterface> $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');
        $product = $productRepository->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductInterface::class, $product, sprintf('There is no product with the code "%s"', $code));

        return $product;
    }

    private function assertNoProduct(string $code): void
    {
        /** @var RepositoryInterface<ProductInterface> $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');
        self::assertNull($productRepository->findOneBy(['code' => $code]), sprintf('There is a product with the code "%s"', $code));
    }

    /**
     * A product the merchant created by hand, with a code the scaffold never picks
     *
     * @param array<string, string> $slugs the slug of the product by locale code
     */
    private function persistHandmadeProduct(array $slugs): void
    {
        $product = new Product();
        $product->setCode('handmade_gift_card');

        foreach ($slugs as $localeCode => $slug) {
            $product->setCurrentLocale($localeCode);
            $product->setFallbackLocale($localeCode);
            $product->setName('Gift card');
            $product->setSlug($slug);
        }

        $this->manager->persist($product);
        $this->manager->flush();
    }

    /**
     * @return list<string|null>
     */
    private function optionCodes(ProductInterface $product): array
    {
        return array_values(array_map(
            static fn (ProductOptionInterface $option): ?string => $option->getCode(),
            $product->getOptions()->toArray(),
        ));
    }
}
