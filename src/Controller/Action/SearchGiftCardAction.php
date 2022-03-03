<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardSearchType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class SearchGiftCardAction
{
    private ?ViewHandlerInterface $viewHandler;

    private FormFactoryInterface $formFactory;

    private ?Environment $twig;

    public function __construct(?ViewHandlerInterface $viewHandler, FormFactoryInterface $formFactory, Environment $twig = null)
    {
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
        $this->twig = $twig;
    }

    public function __invoke(Request $request): Response
    {
        if (null === $this->viewHandler && null === $this->twig) {
            throw new \RuntimeException('Both the view handler and twig environment is null. This means we cannot render the template.');
        }

        $searchGiftCardCommand = new SearchGiftCardCommand();
        $form = $this->formFactory->create(GiftCardSearchType::class, $searchGiftCardCommand);
        $form->handleRequest($request);

        if (null !== $this->twig) {
            return new Response($this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/search.html.twig', [
                'form' => $form->createView(),
                'giftCard' => ($form->isSubmitted() && $form->isValid()) ? $searchGiftCardCommand->getGiftCard() : null,
            ]));
        }

        $view = View::create();
        $view
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/search.html.twig')
            ->setData([
                'form' => $form->createView(),
                'giftCard' => ($form->isSubmitted() && $form->isValid()) ? $searchGiftCardCommand->getGiftCard() : null,
            ]);

        return $this->viewHandler->handle($view);
    }
}
