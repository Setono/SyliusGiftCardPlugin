<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchGiftCardAction
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(
        GiftCardRepositoryInterface $giftCardCodeRepository,
        ViewHandlerInterface $viewHandler,
        ChannelContextInterface $channelContext
    ) {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->viewHandler = $viewHandler;
        $this->channelContext = $channelContext;
    }

    public function __invoke(Request $request): Response
    {
        // todo missing a form here
        $giftCardCode = null;

        /** @var string|null $code */
        $code = $request->get('code', null);
        if (null !== $code) {
            /** @var GiftCardInterface $giftCardCode */
            $giftCardCode = $this->giftCardCodeRepository->findOneEnabledByCodeAndChannel(
                $code,
                $this->channelContext->getChannel()
            );
        }

        $view = View::create();
        $view
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Shop/giftCardSearch.html.twig')
            ->setData([
                'giftCardCode' => $giftCardCode,
            ])
        ;

        return $this->viewHandler->handle($view);
    }
}
