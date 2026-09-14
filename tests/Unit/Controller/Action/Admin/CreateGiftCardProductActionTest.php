<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\CreateGiftCardProductAction;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Every request to the action creates another product, so it may only act on a request that proves the admin
 * meant it: without a valid CSRF token nothing is created
 */
final class CreateGiftCardProductActionTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_rejects_a_request_without_a_valid_csrf_token(): void
    {
        $productFactory = $this->prophesize(GiftCardProductFactoryInterface::class);
        $productFactory->create(Argument::cetera())->shouldNotBeCalled();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::cetera())->shouldNotBeCalled();

        $csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->isTokenValid(new CsrfToken(CreateGiftCardProductAction::CSRF_TOKEN_ID, 'forged'))
            ->willReturn(false)
        ;

        $action = new CreateGiftCardProductAction(
            $productFactory->reveal(),
            $this->prophesize(RepositoryInterface::class)->reveal(),
            $managerRegistry->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $csrfTokenManager->reveal(),
        );

        $this->expectException(AccessDeniedHttpException::class);

        $action($this->request('forged'));
    }

    /** @test */
    public function it_creates_a_disabled_product_and_redirects_to_it_when_the_csrf_token_is_valid(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->getId()->willReturn(42);

        $productFactory = $this->prophesize(GiftCardProductFactoryInterface::class);
        $productFactory->create('gift_card', 'Gift card', Argument::any(), false)->willReturn($product->reveal());

        $productRepository = $this->prophesize(RepositoryInterface::class);
        $productRepository->findOneBy(['code' => 'gift_card'])->willReturn(null);

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->persist($product->reveal())->shouldBeCalled();
        $manager->flush()->shouldBeCalled();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::type('string'))->willReturn($manager->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_admin_product_update', ['id' => 42])->willReturn('/admin/products/42/edit');

        $csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->isTokenValid(new CsrfToken(CreateGiftCardProductAction::CSRF_TOKEN_ID, 'valid'))
            ->willReturn(true)
        ;

        $action = new CreateGiftCardProductAction(
            $productFactory->reveal(),
            $productRepository->reveal(),
            $managerRegistry->reveal(),
            $urlGenerator->reveal(),
            $csrfTokenManager->reveal(),
        );

        $response = $action($this->request('valid'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/products/42/edit', $response->getTargetUrl());
    }

    private function request(string $token): Request
    {
        $request = Request::create('/admin/gift-cards/create-product', 'POST', ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
