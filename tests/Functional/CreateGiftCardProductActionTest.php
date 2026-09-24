<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
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
