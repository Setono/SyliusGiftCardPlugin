<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardCodeRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchGiftCardAction
{
    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    public function __construct(
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        ViewHandlerInterface $viewHandler,
        ChannelRepositoryInterface $channelRepository
    ) {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->viewHandler = $viewHandler;
        $this->channelRepository = $channelRepository;
    }

    public function __invoke(Request $request): Response
    {
        $giftCardCode = null;
        $channelGiftCardCode = null;

        $code = $request->get('code', null);

        if (null !== $code) {
            /** @var GiftCardCodeInterface $giftCardCode */
            $giftCardCode = $this->giftCardCodeRepository->findOneBy(['code' => $code, 'isActive' => true]);
        }

        if (null !== $giftCardCode) {
            $channelGiftCardCode = $this->channelRepository->findOneByCode($giftCardCode->getChannelCode());
        }

        $view = View::create();

        $view
            ->setTemplate('SetonoSyliusGiftCardPlugin:Shop:giftCardSearch.html.twig')
            ->setData([
                'giftCardCode' => $giftCardCode,
                'channelGiftCardCode' => $channelGiftCardCode,
            ])
        ;

        return $this->viewHandler->handle($view);
    }
}
