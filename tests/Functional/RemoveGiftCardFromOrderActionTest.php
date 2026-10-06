<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cart lists each applied gift card with a button that takes it off the cart. The button posts a token tied to
 * the card, so no other page can make the customer's browser remove their gift cards, and a token for one card does
 * not remove another. The requests go through the kernel with the cart the visitor's session names, starting from
 * the cart page the shop renders
 */
final class RemoveGiftCardFromOrderActionTest extends AdminFunctionalTestCase
{
    private const CART_PAGE = 'http://shop.example.test/en_US/cart/';

    private const REMOVED = 'REMOVEME00000001';

    private const KEPT = 'KEEPME0000000001';

    private int $cartId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname('shop.example.test');

        // the shop refuses gift cards until it is set up
        $this->createGiftCardPaymentMethod();

        $cart = new Order();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $this->addItem($cart, 'MUG', 10000);
        $cart->addGiftCard($this->createEnabledGiftCard(self::REMOVED, 3000));
        $cart->addGiftCard($this->createEnabledGiftCard(self::KEPT, 3000));
        $this->manager->persist($cart);
        $this->manager->flush();

        $this->cartId = (int) $cart->getId();
        $this->startSession([sprintf('_sylius.cart.%s', (string) $this->getChannel()->getCode()) => $this->cartId]);
    }

    /** @test */
    public function the_button_on_the_cart_page_takes_the_gift_card_off_the_cart(): void
    {
        $token = $this->tokenOnCartPage(self::REMOVED);

        $response = $this->remove(self::REMOVED, $token);

        self::assertTrue($response->isRedirect('/en_US/cart/'), sprintf('Expected a redirect to the cart, got a %d response', $response->getStatusCode()));
        self::assertSame([self::KEPT], $this->codesOnCart());
    }

    /**
     * @test
     *
     * @dataProvider forgedTokens
     */
    public function it_answers_not_found_and_keeps_the_gift_card_without_the_token_of_that_card(?string $tokenOf): void
    {
        $token = null === $tokenOf ? '' : $this->tokenOnCartPage($tokenOf);

        self::assertSame(404, $this->remove(self::REMOVED, $token)->getStatusCode());
        self::assertSame([self::KEPT, self::REMOVED], $this->codesOnCart());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function forgedTokens(): iterable
    {
        yield 'no token' => [null];
        yield 'the token of another gift card on the cart' => [self::KEPT];
    }

    private function tokenOnCartPage(string $code): string
    {
        $page = $this->request('GET', self::CART_PAGE);
        self::assertSame(200, $page->getStatusCode(), 'the cart page should render');

        return self::valueOf($page, sprintf('//form[@action="/en_US/gift-cards/%s/remove"]//input[@name="_csrf_token"]', $code));
    }

    private function remove(string $code, string $token): Response
    {
        return $this->request('POST', sprintf('http://shop.example.test/en_US/gift-cards/%s/remove', $code), ['_csrf_token' => $token]);
    }

    /**
     * @return list<string> the codes of the gift cards applied to the cart, read back from the database, sorted
     */
    private function codesOnCart(): array
    {
        $this->manager->clear();

        $cart = $this->manager->find(Order::class, $this->cartId);
        self::assertInstanceOf(Order::class, $cart);

        $codes = array_values(array_map(
            static fn (GiftCardInterface $giftCard): string => (string) $giftCard->getCode(),
            $cart->getGiftCards()->toArray(),
        ));
        sort($codes);

        return $codes;
    }
}
