<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\CreateGiftCardPaymentMethodAction;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardPaymentMethodFactoryInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The setup warning's button creates the payment method gift card payments are made with, once: only on a request that
 * proves the admin meant it, and never a second method when one exists by the time the request arrives
 */
final class CreateGiftCardPaymentMethodActionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardPaymentMethodProviderInterface> */
    private ObjectProphecy $paymentMethodProvider;

    /** @var ObjectProphecy<GiftCardPaymentMethodFactoryInterface> */
    private ObjectProphecy $paymentMethodFactory;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->prophesize(GiftCardPaymentMethodProviderInterface::class);
        $this->paymentMethodFactory = $this->prophesize(GiftCardPaymentMethodFactoryInterface::class);
        $this->manager = $this->prophesize(EntityManagerInterface::class);
    }

    /** @test */
    public function it_rejects_a_request_without_a_valid_csrf_token(): void
    {
        $this->paymentMethodProvider->findPaymentMethod()->shouldNotBeCalled();
        $this->paymentMethodFactory->create()->shouldNotBeCalled();
        $this->manager->flush()->shouldNotBeCalled();

        $this->expectException(AccessDeniedHttpException::class);

        ($this->action())($this->request('forged'));
    }

    /** @test */
    public function it_creates_the_payment_method_and_redirects_to_the_gift_card_index_while_it_is_missing(): void
    {
        $paymentMethod = $this->prophesize(PaymentMethodInterface::class)->reveal();

        $this->paymentMethodProvider->findPaymentMethod()->willReturn(null);
        $this->paymentMethodFactory->create()->willReturn($paymentMethod);
        $this->manager->persist($paymentMethod)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $request = $this->request('valid');
        $response = ($this->action())($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/gift-cards/', $response->getTargetUrl());
        self::assertSame(['setono_sylius_gift_card.gift_card.payment_method_created'], $this->flashes($request, 'success'));
        self::assertSame([], $this->flashes($request, 'info'));
    }

    /**
     * Another tab, another admin or a deploy may have created it since the warning with the button was rendered
     *
     * @test
     */
    public function it_creates_nothing_and_says_so_when_the_payment_method_exists(): void
    {
        $this->paymentMethodProvider->findPaymentMethod()->willReturn($this->prophesize(PaymentMethodInterface::class)->reveal());
        $this->paymentMethodFactory->create()->shouldNotBeCalled();
        $this->manager->persist(Argument::any())->shouldNotBeCalled();
        $this->manager->flush()->shouldNotBeCalled();

        $request = $this->request('valid');
        $response = ($this->action())($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/gift-cards/', $response->getTargetUrl());
        self::assertSame(['setono_sylius_gift_card.gift_card.payment_method_already_exists'], $this->flashes($request, 'info'));
        self::assertSame([], $this->flashes($request, 'success'));
    }

    /**
     * A double click sends two requests that both find no method. The code is unique, so the one that flushes last
     * fails, and is answered like a request that found the method rather than with an error page
     *
     * @test
     */
    public function it_says_the_payment_method_exists_when_a_request_at_the_same_time_created_it_first(): void
    {
        $paymentMethod = $this->prophesize(PaymentMethodInterface::class)->reveal();

        $this->paymentMethodProvider->findPaymentMethod()->willReturn(null);
        $this->paymentMethodFactory->create()->willReturn($paymentMethod);
        $this->manager->persist($paymentMethod)->shouldBeCalled();
        // A double, because the exception's constructor differs between the DBAL 2 and 3 the plugin supports
        $this->manager->flush()->willThrow($this->prophesize(UniqueConstraintViolationException::class)->reveal());

        $request = $this->request('valid');
        $response = ($this->action())($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/gift-cards/', $response->getTargetUrl());
        self::assertSame(['setono_sylius_gift_card.gift_card.payment_method_already_exists'], $this->flashes($request, 'info'));
        self::assertSame([], $this->flashes($request, 'success'));
    }

    private function action(): CreateGiftCardPaymentMethodAction
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::type('string'))->willReturn($this->manager->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_index')->willReturn('/admin/gift-cards/');

        $csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $csrfTokenManager->isTokenValid(new CsrfToken(CreateGiftCardPaymentMethodAction::CSRF_TOKEN_ID, 'valid'))->willReturn(true);
        $csrfTokenManager->isTokenValid(new CsrfToken(CreateGiftCardPaymentMethodAction::CSRF_TOKEN_ID, 'forged'))->willReturn(false);

        return new CreateGiftCardPaymentMethodAction(
            $this->paymentMethodProvider->reveal(),
            $this->paymentMethodFactory->reveal(),
            $managerRegistry->reveal(),
            $urlGenerator->reveal(),
            $csrfTokenManager->reveal(),
        );
    }

    private function request(string $token): Request
    {
        $request = Request::create('/admin/gift-cards/create-payment-method', 'POST', ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    /**
     * @return list<mixed>
     */
    private function flashes(Request $request, string $type): array
    {
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);

        return array_values($session->getFlashBag()->peek($type));
    }
}
