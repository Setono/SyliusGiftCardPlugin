<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ResendGiftCardEmailAction
{
    private GiftCardEmailManagerInterface $giftCardEmailManager;

    public function __construct(GiftCardEmailManagerInterface $giftCardEmailManager)
    {
        $this->giftCardEmailManager = $giftCardEmailManager;
    }

    public function __invoke(GiftCardInterface $data): Response
    {
        $giftCard = $data;
        if ($giftCard->getOrder() instanceof OrderInterface) {
            $this->giftCardEmailManager->sendEmailWithGiftCardsFromOrder($giftCard->getOrder(), [$giftCard]);
        } elseif ($giftCard->getCustomer() instanceof CustomerInterface) {
            $this->giftCardEmailManager->sendEmailToCustomerWithGiftCard($giftCard->getCustomer(), $giftCard);
        } else {
            throw new BadRequestHttpException();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
