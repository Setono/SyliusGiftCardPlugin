<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardSearchType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class SearchGiftCardAction
{
    private FormFactoryInterface $formFactory;

    private Environment $twig;

    public function __construct(
        FormFactoryInterface $formFactory,
        Environment $twig
    ) {
        $this->formFactory = $formFactory;
        $this->twig = $twig;
    }

    public function __invoke(Request $request): Response
    {
        $searchGiftCardCommand = new SearchGiftCardCommand();
        $form = $this->formFactory->create(GiftCardSearchType::class, $searchGiftCardCommand);
        $form->handleRequest($request);

        return new Response($this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/search.html.twig', [
            'form' => $form->createView(),
            'giftCard' => ($form->isSubmitted() && $form->isValid()) ? $searchGiftCardCommand->getGiftCard() : null,
        ]));
    }
}
