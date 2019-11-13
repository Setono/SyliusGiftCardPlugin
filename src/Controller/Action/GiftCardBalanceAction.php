<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardBalanceCollection;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The purpose of this class is to show the gift card balance, i.e. what amount is still available on enabled gift cards
 */
final class GiftCardBalanceAction
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    public function __construct(
        GiftCardRepositoryInterface $giftCardCodeRepository,
        ViewHandlerInterface $viewHandler
    ) {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->viewHandler = $viewHandler;
    }

    public function __invoke(Request $request): Response
    {
        $giftCardBalanceCollection = GiftCardBalanceCollection::createFromGiftCards(
            $this->giftCardCodeRepository->findAll()
        );

        $view = View::create();
        $view
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Admin/giftCardBalance.html.twig')
            ->setData([
                'giftCardBalanceCollection' => $giftCardBalanceCollection,
            ])
        ;

        return $this->viewHandler->handle($view);
    }
}
