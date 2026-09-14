<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Controller\Action\RemoveGiftCardFromOrderAction;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The cart template builds the CSRF token id from the stored code, while the route parameter arrives however the
 * customer's browser spelled it, so the action has to bring the parameter to the canonical form before comparing
 * the two and before asking the applicator to remove the card
 */
final class RemoveGiftCardFromOrderActionTest extends TestCase
{
    use ProphecyTrait;

    private const CODE = 'ABCDEFGHJKMNPQRS';

    /**
     * @test
     *
     * @dataProvider spellingsOfTheCode
     */
    public function it_removes_the_gift_card_named_by_any_spelling_of_its_code(string $parameter): void
    {
        $order = new Order();

        $applicator = $this->prophesize(GiftCardApplicatorInterface::class);
        $applicator->remove($order, self::CODE)->shouldBeCalledOnce();

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->flush()->shouldBeCalledOnce();

        $csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->isTokenValid(Argument::that(static fn (CsrfToken $token): bool => 'setono_remove_gift_card_' . self::CODE === $token->getId() && 'valid' === $token->getValue()))
            ->willReturn(true)
        ;

        $request = $this->request('valid');
        $response = $this->action($order, $applicator->reveal(), $csrfTokenManager->reveal(), $manager->reveal())($request, $parameter);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/en_US/cart/', $response->headers->get('Location'));

        /** @var Session $session */
        $session = $request->getSession();
        self::assertSame(['setono_sylius_gift_card.gift_card_removed'], $session->getFlashBag()->get('success'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spellingsOfTheCode(): iterable
    {
        yield 'the stored code' => [self::CODE];
        yield 'grouped by dashes, in lower case' => ['abcd-efgh-jkmn-pqrs'];
        yield 'grouped by spaces' => ['ABCD EFGH JKMN PQRS'];
    }

    /** @test */
    public function it_refuses_a_request_without_a_valid_token(): void
    {
        $applicator = $this->prophesize(GiftCardApplicatorInterface::class);
        $applicator->remove(Argument::cetera())->shouldNotBeCalled();

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->flush()->shouldNotBeCalled();

        $csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $csrfTokenManager->isTokenValid(Argument::type(CsrfToken::class))->willReturn(false);

        $action = $this->action(new Order(), $applicator->reveal(), $csrfTokenManager->reveal(), $manager->reveal());

        $this->expectException(NotFoundHttpException::class);

        $action($this->request('forged'), self::CODE);
    }

    private function action(
        Order $order,
        GiftCardApplicatorInterface $applicator,
        CsrfTokenManagerInterface $csrfTokenManager,
        EntityManagerInterface $manager,
    ): RemoveGiftCardFromOrderAction {
        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($order);

        $redirectUrlResolver = $this->prophesize(RedirectUrlResolverInterface::class);
        $redirectUrlResolver->getUrlToRedirectTo(Argument::type(Request::class), 'sylius_shop_cart_summary')->willReturn('/en_US/cart/');

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::type('string'))->willReturn($manager);

        return new RemoveGiftCardFromOrderAction(
            $cartContext->reveal(),
            $applicator,
            $redirectUrlResolver->reveal(),
            $csrfTokenManager,
            new GiftCardCodeNormalizer(),
            $managerRegistry->reveal(),
        );
    }

    private function request(string $token): Request
    {
        $request = Request::create('/en_US/gift-cards/' . self::CODE . '/remove', 'POST', ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
