<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityChecker;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\SendGiftCardEmailAction;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Sending reaches the customer, so the action may only act on a request that proves the admin meant it:
 * without a valid CSRF token nothing is sent. And only a card the customer can use is sent, as the email on
 * creation is only sent for one: any other would arrive as a gift that does not work
 */
final class SendGiftCardEmailActionTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_rejects_a_request_without_a_valid_csrf_token(): void
    {
        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->find(Argument::cetera())->shouldNotBeCalled();

        $action = new SendGiftCardEmailAction(
            $giftCardRepository->reveal(),
            $emailManager->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $this->csrfTokenManager('forged', false)->reveal(),
            new GiftCardEligibilityChecker(),
        );

        $this->expectException(AccessDeniedHttpException::class);

        $action($this->request('forged'), 42);
    }

    /** @test */
    public function it_sends_the_gift_card_and_redirects_back_to_it(): void
    {
        $giftCard = self::giftCard('customer@example.com');

        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard($giftCard)->shouldBeCalledOnce();

        $request = $this->request('valid');
        $response = $this->action($giftCard, $emailManager->reveal())($request, 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/gift-cards/42', $response->getTargetUrl());
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.email_sent'],
            $this->flashes($request, 'success'),
        );
    }

    /**
     * The email manager silently does nothing without an address, so a card with no customer would leave the
     * admin thinking it was sent
     *
     * @test
     */
    public function it_reports_that_a_gift_card_without_a_customer_email_could_not_be_sent(): void
    {
        $giftCard = self::giftCard(null);

        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $request = $this->request('valid');
        $response = $this->action($giftCard, $emailManager->reveal())($request, 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.email_not_sent_no_customer'],
            $this->flashes($request, 'error'),
        );
    }

    /**
     * The admin is told why, so they know what to change before sending it
     *
     * @test
     *
     * @dataProvider unusableGiftCards
     */
    public function it_does_not_send_a_gift_card_the_customer_cannot_use(GiftCardInterface $giftCard, string $flash): void
    {
        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $request = $this->request('valid');
        $response = $this->action($giftCard, $emailManager->reveal())($request, 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/gift-cards/42', $response->getTargetUrl());
        self::assertSame([$flash], $this->flashes($request, 'error'));
        self::assertSame([], $this->flashes($request, 'success'));
    }

    /**
     * @return iterable<string, array{GiftCardInterface, string}>
     */
    public static function unusableGiftCards(): iterable
    {
        $disabled = self::giftCard('customer@example.com');
        $disabled->disable();
        yield 'disabled' => [$disabled, 'setono_sylius_gift_card.gift_card.email_not_sent_disabled'];

        // A card waiting in a cart: disabled until its order is paid
        $pending = self::giftCard('customer@example.com');
        $pending->disable();
        $pending->setOrderItemUnit(new OrderItemUnit(new OrderItem()));
        yield 'pending' => [$pending, 'setono_sylius_gift_card.gift_card.email_not_sent_disabled'];

        $expired = self::giftCard('customer@example.com');
        $expired->setExpiresAt(new \DateTimeImmutable('-1 day'));
        yield 'expired' => [$expired, 'setono_sylius_gift_card.gift_card.email_not_sent_expired'];

        $spent = self::giftCard('customer@example.com');
        $spent->setAmount(0);
        yield 'without a balance' => [$spent, 'setono_sylius_gift_card.gift_card.email_not_sent_no_balance'];

        // Nobody to send it to either, but the card is what the admin has to change first
        $disabledWithoutCustomer = self::giftCard(null);
        $disabledWithoutCustomer->disable();
        yield 'disabled, without a customer' => [$disabledWithoutCustomer, 'setono_sylius_gift_card.gift_card.email_not_sent_disabled'];
    }

    /**
     * An application may make more cards unusable than the eligibility checker names a reason for. Such a card is not
     * sent either, and the admin is told it cannot be used
     *
     * @test
     */
    public function it_does_not_send_a_gift_card_that_cannot_be_used_for_a_reason_the_checker_does_not_name(): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->isUsable()->willReturn(false);

        $eligibilityChecker = $this->prophesize(GiftCardEligibilityCheckerInterface::class);
        $eligibilityChecker->getIneligibilityReason($giftCard->reveal())->willReturn(null);

        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $request = $this->request('valid');
        $this->action($giftCard->reveal(), $emailManager->reveal(), $eligibilityChecker->reveal())($request, 42);

        self::assertSame(['setono_sylius_gift_card.gift_card.email_not_sent_not_usable'], $this->flashes($request, 'error'));
    }

    /** @test */
    public function it_answers_not_found_for_an_unknown_gift_card(): void
    {
        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->find(42)->willReturn(null);

        $action = new SendGiftCardEmailAction(
            $giftCardRepository->reveal(),
            $emailManager->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $this->csrfTokenManager('valid', true)->reveal(),
            new GiftCardEligibilityChecker(),
        );

        $this->expectException(NotFoundHttpException::class);

        $action($this->request('valid'), 42);
    }

    private function action(
        GiftCardInterface $giftCard,
        GiftCardEmailManagerInterface $emailManager,
        GiftCardEligibilityCheckerInterface $eligibilityChecker = new GiftCardEligibilityChecker(),
    ): SendGiftCardEmailAction {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->find(42)->willReturn($giftCard);

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_gift_card_admin_gift_card_show', ['id' => 42])
            ->willReturn('/admin/gift-cards/42')
        ;

        return new SendGiftCardEmailAction(
            $giftCardRepository->reveal(),
            $emailManager,
            $urlGenerator->reveal(),
            $this->csrfTokenManager('valid', true)->reveal(),
            $eligibilityChecker,
        );
    }

    /**
     * A card the customer can use: enabled, not expired and holding a balance
     */
    private static function giftCard(?string $customerEmail): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->enable();
        $giftCard->setAmount(5000);

        if (null !== $customerEmail) {
            $customer = new Customer();
            $customer->setEmail($customerEmail);
            $giftCard->setCustomer($customer);
        }

        return $giftCard;
    }

    /**
     * @return \Prophecy\Prophecy\ObjectProphecy<CsrfTokenManagerInterface>
     */
    private function csrfTokenManager(string $token, bool $valid): object
    {
        $csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->isTokenValid(new CsrfToken(SendGiftCardEmailAction::CSRF_TOKEN_ID, $token))
            ->willReturn($valid)
        ;

        return $csrfTokenManager;
    }

    /**
     * @return list<string>
     */
    private function flashes(Request $request, string $type): array
    {
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);

        /** @var list<string> $flashes */
        $flashes = $session->getFlashBag()->get($type);

        return $flashes;
    }

    private function request(string $token): Request
    {
        $request = Request::create('/admin/gift-cards/42/send-email', 'POST', ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
