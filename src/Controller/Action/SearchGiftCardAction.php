<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardSearchType;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchGiftCardAction
{
    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var FormFactoryInterface */
    private $formFactory;

    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        FormFactoryInterface $formFactory,
        GiftCardRepositoryInterface $giftCardRepository,
        ChannelContextInterface $channelContext
    ) {
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
        $this->giftCardRepository = $giftCardRepository;
        $this->channelContext = $channelContext;
    }

    public function __invoke(Request $request): Response
    {
        $searchGiftCardCommand = new SearchGiftCardCommand();
        $form = $this->formFactory->create(GiftCardSearchType::class, $searchGiftCardCommand);
        $form->handleRequest($request);

        $giftCard = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $giftCard = $this->giftCardRepository->findOneEnabledByCodeAndChannel($searchGiftCardCommand->getCode(), $this->channelContext->getChannel());
        }

        $view = View::create();
        $view
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Shop/giftCardSearch.html.twig')
            ->setData([
                'form' => $form->createView(),
                'giftCard' => $giftCard,
            ])
        ;

        return $this->viewHandler->handle($view);
    }
}
