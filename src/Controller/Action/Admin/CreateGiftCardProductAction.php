<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Sylius\Component\Core\Model\ProductInterface as SyliusProductInterface;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Scaffolds a ready-to-edit gift card product (with a delivery option and virtual + physical variants) so the
 * merchant does not have to assemble it by hand. It is created disabled so the merchant can review it first.
 *
 * Every request creates another product, so the route only takes a POST carrying a CSRF token: a plain link
 * could be hit by a browser prefetch or an <img src> on any page the admin visits
 */
final class CreateGiftCardProductAction
{
    use ORMTrait;

    /**
     * The id of the CSRF token the request must carry; the grid action rendering the form uses the same id
     */
    public const CSRF_TOKEN_ID = 'setono_create_gift_card_product';

    private const CODE = 'gift_card';

    /**
     * @param RepositoryInterface<SyliusProductInterface> $productRepository
     * @param RepositoryInterface<ProductTranslationInterface> $productTranslationRepository
     */
    public function __construct(
        private readonly GiftCardProductFactoryInterface $productFactory,
        private readonly RepositoryInterface $productRepository,
        private readonly RepositoryInterface $productTranslationRepository,
        ManagerRegistry $managerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $product = $this->productFactory->create($this->provideCode(), 'Gift card', enabled: false);

        $manager = $this->getManager($product);
        $manager->persist($product);
        $manager->flush();

        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add('success', 'setono_sylius_gift_card.gift_card.product_created');
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_product_update', [
            'id' => $product->getId(),
        ]));
    }

    /**
     * The first gift card product a shop creates should just be "gift_card"; only a shop that wants more than
     * one needs to tell them apart
     */
    private function provideCode(): string
    {
        $suffix = 1;

        do {
            $code = 1 === $suffix ? self::CODE : sprintf('%s_%d', self::CODE, $suffix);
            ++$suffix;
        } while ($this->isTaken($code));

        return $code;
    }

    /**
     * The product gets a slug derived from its code in every locale, and a slug is unique per locale, so a code whose
     * slug another product already uses in any locale is as taken as the code itself. A merchant who created a
     * "Gift card" product by hand has the slug "gift-card", which Sylius' admin derives from that name
     */
    private function isTaken(string $code): bool
    {
        return null !== $this->productRepository->findOneBy(['code' => $code]) ||
            null !== $this->productTranslationRepository->findOneBy(['slug' => $this->productFactory->getSlug($code)]);
    }
}
