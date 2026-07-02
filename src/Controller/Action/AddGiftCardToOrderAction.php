<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\DoctrineObjectManagerTrait\ORM\ORMManagerTrait;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class AddGiftCardToOrderAction
{
    use ORMManagerTrait;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly CartContextInterface $cartContext,
        private readonly GiftCardApplicatorInterface $giftCardApplicator,
        private readonly RedirectUrlResolverInterface $redirectRouteResolver,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->cartContext->getCart();
        if (null === $order) {
            throw new NotFoundHttpException();
        }

        $command = new AddGiftCardToOrderCommand();
        $form = $this->formFactory->create(AddGiftCardToOrderType::class, $command);
        $form->handleRequest($request);

        $session = $request->getSession();

        if ($form->isSubmitted() && $form->isValid()) {
            $giftCard = $command->getGiftCard();
            Assert::notNull($giftCard);

            $this->giftCardApplicator->apply($order, $giftCard);
            $this->getManager($order)->flush();

            if ($session instanceof Session) {
                $session->getFlashBag()->add('success', 'setono_sylius_gift_card.gift_card_added');
            }
        } elseif ($session instanceof Session) {
            foreach ($this->collectErrors($form) as $error) {
                $session->getFlashBag()->add('error', $error);
            }
        }

        return new RedirectResponse(
            $this->redirectRouteResolver->getUrlToRedirectTo($request, 'sylius_shop_cart_summary'),
        );
    }

    /**
     * @return list<string>
     */
    private function collectErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];

        /** @var FormError $error */
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        if ([] === $errors) {
            $errors[] = 'setono_sylius_gift_card.gift_card.could_not_be_applied';
        }

        return $errors;
    }
}
