<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;

/**
 * A gift card code is a bearer token, and the cart's gift card form is the one place in the shop where codes can be
 * guessed. So a code no gift card has gets the very answer a gift card that exists but cannot be used gets, or the form
 * would tell a guesser which codes exist. What a customer is told is decided by the form, its data transformer, the
 * command's constraints, the action and the translator together, so the form is posted through the kernel, with the
 * CSRF token the cart page renders or without one, and the answer is read from the cart page the action redirects to
 */
final class AddGiftCardToOrderActionTest extends AdminFunctionalTestCase
{
    private const CART_PAGE = 'http://shop.example.test/en_US/cart/';

    private const FORM = '//form[@action="/en_US/gift-cards"]';

    private const ERRORS = '//*[contains(concat(" ", normalize-space(@class), " "), " negative ")]//*[@data-test-flash-messages]';

    private const GENERIC_ERROR = 'The gift card could not be applied to your order.';

    private const CSRF_ERROR = 'The CSRF token is invalid. Please try to resubmit the form.';

    private const UNKNOWN = 'NOSUCHGIFTCARD01';

    private int $cartId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname('shop.example.test');

        // the shop refuses every gift card, before looking at the code, until it is set up
        $this->createGiftCardPaymentMethod();

        $cart = new Order();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $this->addItem($cart, 'MUG', 10000);
        $this->manager->persist($cart);

        // the data transformer finds no disabled card, while the eligibility constraint refuses the other two
        $this->createEnabledGiftCard('DISABLED00000001', 5000)->disable();
        $this->createEnabledGiftCard('EXPIRED000000001', 5000)->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->createEnabledGiftCard('EUROS00000000001', 5000, 'EUR');

        $this->manager->flush();

        $this->cartId = (int) $cart->getId();
        $this->startSession([sprintf('_sylius.cart.%s', (string) $this->getChannel()->getCode()) => $this->cartId]);
    }

    /**
     * The code matches no gift card, so it fails in the data transformer and leaves the command without one, which
     * fails the NotBlank constraint as well. Symfony drops the violations of a field whose transformation failed,
     * though, so the field's invalid_message is all the action is given
     *
     * @test
     */
    public function it_refuses_a_code_no_gift_card_has_with_the_generic_message_alone(): void
    {
        self::assertSame([self::GENERIC_ERROR], $this->errorsWhenApplying(self::UNKNOWN));
    }

    /**
     * @test
     *
     * @dataProvider unusableGiftCards
     */
    public function it_refuses_a_gift_card_that_cannot_be_used_in_the_words_it_refuses_an_unknown_code(string $code): void
    {
        self::assertSame($this->errorsWhenApplying(self::UNKNOWN), $this->errorsWhenApplying($code));
    }

    /**
     * A request without the token the cart page renders is refused, but the code is looked up all the same, so the
     * answer must not tell an unknown code from an unusable one either. Leaving the token out would otherwise be a way
     * to find out which codes exist
     *
     * @test
     *
     * @dataProvider unusableGiftCards
     */
    public function it_answers_an_unknown_code_and_an_unusable_gift_card_alike_without_the_token_too(string $code): void
    {
        $unknown = $this->errorsWhenApplying(self::UNKNOWN, false);

        self::assertSame($unknown, $this->errorsWhenApplying($code, false));
        self::assertSame([self::CSRF_ERROR, self::GENERIC_ERROR], $unknown);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableGiftCards(): iterable
    {
        yield 'disabled' => ['DISABLED00000001'];
        yield 'expired' => ['EXPIRED000000001'];
        yield 'in another currency than the cart' => ['EUROS00000000001'];
    }

    /**
     * Nothing entered leaves the command just as empty as a code no gift card has, and here the NotBlank constraint
     * does speak, because the transformation succeeded
     *
     * @test
     */
    public function it_asks_for_a_code_when_none_was_entered(): void
    {
        self::assertSame(['Please enter a gift card code'], $this->errorsWhenApplying(''));
    }

    /**
     * Also shows that the action accepts the token the cart page renders, so no answer above is a refused token's
     *
     * @test
     */
    public function it_applies_a_gift_card_that_can_be_used(): void
    {
        $this->createEnabledGiftCard('USABLE0000000001', 5000);
        $this->manager->flush();

        self::assertSame([], $this->errorsWhenApplying('USABLE0000000001'));
        self::assertSame(['USABLE0000000001'], $this->codesOnCart());
    }

    /**
     * Submits the code with the cart page's form, with the token the page renders or without any
     *
     * @return list<string> the errors the cart page shows afterwards
     */
    private function errorsWhenApplying(string $code, bool $withToken = true): array
    {
        $page = $this->request('GET', self::CART_PAGE);
        self::assertSame(200, $page->getStatusCode(), 'the cart page should render');

        $data = ['giftCard' => $code];
        if ($withToken) {
            $data['_token'] = self::valueOf($page, self::FORM . '//input[@name="setono_sylius_gift_card_add_gift_card_to_order[_token]"]');
        }

        $response = $this->request('POST', 'http://shop.example.test/en_US/gift-cards', ['setono_sylius_gift_card_add_gift_card_to_order' => $data]);
        self::assertTrue($response->isRedirect('/en_US/cart/'), sprintf('Expected a redirect to the cart, got a %d response', $response->getStatusCode()));

        $cart = $this->request('GET', self::CART_PAGE);
        self::assertSame(200, $cart->getStatusCode(), 'the cart page should render');

        return self::textsOf($cart, self::ERRORS);
    }

    /**
     * @return list<string> the codes of the gift cards applied to the cart, read back from the database
     */
    private function codesOnCart(): array
    {
        $this->manager->clear();

        $cart = $this->manager->find(Order::class, $this->cartId);
        self::assertInstanceOf(Order::class, $cart);

        return array_values(array_map(
            static fn (GiftCardInterface $giftCard): string => (string) $giftCard->getCode(),
            $cart->getGiftCards()->toArray(),
        ));
    }
}
