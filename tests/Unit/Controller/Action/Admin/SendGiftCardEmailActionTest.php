<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\SendGiftCardEmailAction;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Sending reaches the customer, so the action may only act on a request that proves the admin meant it:
 * without a valid CSRF token nothing is sent
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
        );

        $this->expectException(AccessDeniedHttpException::class);

        $action($this->request('forged'), 42);
    }

    /** @test */
    public function it_sends_the_gift_card_and_redirects_back_to_it(): void
    {
        $giftCard = $this->giftCard('customer@example.com');

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
        $giftCard = $this->giftCard(null);

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

    private function action(GiftCardInterface $giftCard, GiftCardEmailManagerInterface $emailManager): SendGiftCardEmailAction
    {
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
        );
    }

    private function giftCard(?string $customerEmail): GiftCardInterface
    {
        $giftCard = new GiftCard();

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
