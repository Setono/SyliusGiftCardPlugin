<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Setono\DoctrineObjectManagerTrait\ORM\ORMManagerTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\AdjustGiftCardBalanceType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class AdjustGiftCardBalanceAction
{
    use ORMManagerTrait;

    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException();
        }

        $form = $this->formFactory->create(AdjustGiftCardBalanceType::class, null, [
            'currency' => (string) $giftCard->getCurrencyCode(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{amount: int, reason: string} $data */
            $data = $form->getData();

            $this->balanceOperator->adjust($giftCard, $data['amount'], $data['reason']);
            $this->getManager($giftCard)->flush();

            $session = $request->getSession();
            if ($session instanceof Session) {
                $session->getFlashBag()->add('success', 'setono_sylius_gift_card.gift_card.balance_adjusted');
            }

            return new RedirectResponse($this->urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_update', ['id' => $id]));
        }

        return new Response($this->twig->render('@SetonoSyliusGiftCardPlugin/admin/gift_card/adjust_balance.html.twig', [
            'giftCard' => $giftCard,
            'form' => $form->createView(),
        ]));
    }
}
