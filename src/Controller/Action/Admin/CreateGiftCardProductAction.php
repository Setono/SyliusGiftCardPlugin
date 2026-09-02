<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Scaffolds a ready-to-edit gift card product (with a delivery option and virtual + physical variants) so the
 * merchant does not have to assemble it by hand. It is created disabled so the merchant can review it first
 */
final class CreateGiftCardProductAction
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardProductFactoryInterface $productFactory,
        ManagerRegistry $managerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $product = $this->productFactory->create(
            'gift_card_' . bin2hex(random_bytes(4)),
            'Gift card',
            enabled: false,
        );

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
}
