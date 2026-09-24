<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * The cart summary lists each applied gift card with what it covers, and below the totals what the gift cards cover
 * together and what is left to pay. The browser suite applies a single card that covers the whole cart, so the
 * partial, stacked and zero coverage cases are rendered here, through the plugin's Twig functions as the kernel
 * wires them, with the cart templates the plugin hooks into the summary
 */
final class CartGiftCardFiguresTest extends GiftCardFunctionalTestCase
{
    private const GIFT_CARDS_TEMPLATE = '@SetonoSyliusGiftCardPlugin/shop/cart/_gift_cards.html.twig';

    private const TOTALS_TEMPLATE = '@SetonoSyliusGiftCardPlugin/shop/cart/_gift_card_totals.html.twig';

    protected function setUp(): void
    {
        parent::setUp();

        $channel = $this->getChannel();
        $channel->setHostname('shop.example.test');
        $this->manager->flush();

        // the money macro formats in the channel, currency and locale of the current request, as it does in the shop
        $request = Request::create('http://shop.example.test/en_US/cart/');
        $request->attributes->set('_locale', 'en_US');
        $request->setSession(new Session(new MockArraySessionStorage()));

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        // the shop routes are prefixed with the locale, which the router listener would take from the request
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $router->getContext()->fromRequest($request);
        $router->getContext()->setParameter('_locale', 'en_US');
    }

    /** @test */
    public function a_cart_without_gift_cards_only_offers_to_apply_one(): void
    {
        $cart = $this->createCart(10000);

        $giftCards = $this->render(self::GIFT_CARDS_TEMPLATE, $cart);
        self::assertSame(1, self::matchCount($giftCards, '//*[@data-test-apply-gift-card-button]'));
        self::assertSame(0, self::matchCount($giftCards, '//*[@data-test-applied-gift-cards]'));

        self::assertSame(0, self::matchCount($this->render(self::TOTALS_TEMPLATE, $cart), '//*[@data-test-gift-card-totals]'));
    }

    /** @test */
    public function each_card_shows_what_it_covers_and_the_totals_show_what_is_left_to_pay(): void
    {
        $cart = $this->createCart(10000);
        $cart->addGiftCard($this->createGiftCard('FIGURES000000001', 3000));
        $cart->addGiftCard($this->createGiftCard('FIGURES000000002', 5000));

        $giftCards = $this->render(self::GIFT_CARDS_TEMPLATE, $cart);
        self::assertSame('-$30.00', $this->coveredBy($giftCards, 'FIGURES000000001'));
        self::assertSame('-$50.00', $this->coveredBy($giftCards, 'FIGURES000000002'));

        $totals = $this->render(self::TOTALS_TEMPLATE, $cart);
        self::assertSame('-$80.00', $this->amountIn($totals, 'gift-cards-total'));
        self::assertSame('$20.00', $this->amountIn($totals, 'gift-cards-remaining-total'));
    }

    /**
     * A card is only charged what the cards before it left, so the row shows that rather than its balance
     *
     * @test
     */
    public function a_card_shows_what_it_covers_rather_than_its_balance(): void
    {
        $cart = $this->createCart(10000);
        $cart->addGiftCard($this->createGiftCard('FIGURES000000003', 3000));
        $cart->addGiftCard($this->createGiftCard('FIGURES000000004', 9000));

        $giftCards = $this->render(self::GIFT_CARDS_TEMPLATE, $cart);
        self::assertSame('-$30.00', $this->coveredBy($giftCards, 'FIGURES000000003'));
        self::assertSame('-$70.00', $this->coveredBy($giftCards, 'FIGURES000000004'));

        $totals = $this->render(self::TOTALS_TEMPLATE, $cart);
        self::assertSame('-$100.00', $this->amountIn($totals, 'gift-cards-total'));
        self::assertSame('$0.00', $this->amountIn($totals, 'gift-cards-remaining-total'));
    }

    /**
     * A gift card cannot pay for another gift card, so a card applied to a cart that only buys gift cards covers
     * nothing. It stays listed so the customer can remove it, but there is nothing to deduct from the totals
     *
     * @test
     */
    public function a_card_that_covers_nothing_is_listed_without_adding_totals(): void
    {
        $cart = $this->createCart(10000, true);
        $cart->addGiftCard($this->createGiftCard('FIGURES000000005', 5000));

        $giftCards = $this->render(self::GIFT_CARDS_TEMPLATE, $cart);
        self::assertSame('$0.00', $this->coveredBy($giftCards, 'FIGURES000000005'));
        self::assertSame(1, self::matchCount($giftCards, '//*[@data-test-remove-gift-card-button="FIGURES000000005"]'));

        self::assertSame(0, self::matchCount($this->render(self::TOTALS_TEMPLATE, $cart), '//*[@data-test-gift-card-totals]'));
    }

    private function render(string $template, Order $cart): \DOMXPath
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8"><body>' . $twig->render($template, ['cart' => $cart]) . '</body>', \LIBXML_NOERROR);

        return new \DOMXPath($document);
    }

    /**
     * The amount in the applied gift card row of the given code
     */
    private function coveredBy(\DOMXPath $html, string $code): string
    {
        return $this->text($html, sprintf('//tr[@data-test-applied-gift-card="%s"]/td[2]', $code));
    }

    /**
     * The amount in the totals row carrying the given test attribute
     */
    private function amountIn(\DOMXPath $html, string $row): string
    {
        return $this->text($html, sprintf('//tr[@data-test-%s]/td[2]', $row));
    }

    private function text(\DOMXPath $html, string $expression): string
    {
        $nodes = self::query($html, $expression);
        self::assertSame(1, $nodes->length, sprintf('expected exactly one match for %s', $expression));

        return trim((string) $nodes->item(0)?->nodeValue);
    }

    private static function matchCount(\DOMXPath $html, string $expression): int
    {
        return self::query($html, $expression)->length;
    }

    /**
     * @return \DOMNodeList<\DOMNameSpaceNode|\DOMNode>
     */
    private static function query(\DOMXPath $html, string $expression): \DOMNodeList
    {
        $nodes = $html->query($expression);
        self::assertInstanceOf(\DOMNodeList::class, $nodes, sprintf('%s is not a valid expression', $expression));

        return $nodes;
    }

    /**
     * A cart with one product costing the given total, either an ordinary product or a gift card product
     */
    private function createCart(int $total, bool $giftCardProduct = false): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('PRODUCT');
        $product->setName('Product');
        $product->setSlug('product');
        $product->setGiftCard($giftCardProduct);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('PRODUCT_VARIANT');
        $variant->setName('Product');
        $variant->setProduct($product);
        $this->manager->persist($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($total);
        new OrderItemUnit($item);

        $cart = new Order();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $cart->addItem($item);

        $this->manager->persist($cart);
        $this->manager->flush();

        self::assertSame($total, $cart->getTotal());

        return $cart;
    }

    private function createGiftCard(string $code, int $amount): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }
}
