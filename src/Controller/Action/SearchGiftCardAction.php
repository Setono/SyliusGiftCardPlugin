<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardSearchType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchGiftCardAction
{
    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var FormFactoryInterface */
    private $formFactory;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        FormFactoryInterface $formFactory
    ) {
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
    }

    public function __invoke(Request $request): Response
    {
        $searchGiftCardCommand = new SearchGiftCardCommand();
        $form = $this->formFactory->create(GiftCardSearchType::class, $searchGiftCardCommand);
        $form->handleRequest($request);

        $view = View::create();
        $view
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/search.html.twig')
            ->setData([
                'form' => $form->createView(),
                'giftCard' => ($form->isSubmitted() && $form->isValid()) ? $searchGiftCardCommand->getGiftCard() : null,
            ])
        ;

        return $this->viewHandler->handle($view);
    }
}
