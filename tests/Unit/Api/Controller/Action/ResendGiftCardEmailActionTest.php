<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Controller\Action;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\ResendGiftCardEmailAction;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\OrderItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItemUnit;

final class ResendGiftCardEmailActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_resends_email_for_order(): void
    {
        $giftCard = new GiftCard();
        $order = new Order();
        $orderItem = new OrderItem();
        $orderItem->setOrder($order);
        $orderItemUnit = new OrderItemUnit($orderItem);

        $giftCard->setOrderItemUnit($orderItemUnit);

        $expectedResponse = new Response(null, Response::HTTP_NO_CONTENT);

        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $giftCardEmailManager->sendEmailWithGiftCardsFromOrder($order, [$giftCard])->shouldBeCalled();

        $resendGiftCardEmailAction = new ResendGiftCardEmailAction($giftCardEmailManager->reveal());
        $response = $resendGiftCardEmailAction($giftCard);

        self::assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     */
    public function it_resends_email_for_customer(): void
    {
        $giftCard = new GiftCard();
        $customer = new Customer();

        $giftCard->setCustomer($customer);

        $expectedResponse = new Response(null, Response::HTTP_NO_CONTENT);

        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $giftCardEmailManager->sendEmailToCustomerWithGiftCard($customer, $giftCard)->shouldBeCalled();

        $resendGiftCardEmailAction = new ResendGiftCardEmailAction($giftCardEmailManager->reveal());
        $response = $resendGiftCardEmailAction($giftCard);

        self::assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     */
    public function it_throws_error_if_gift_card_has_no_order_nor_customer(): void
    {
        $giftCard = new GiftCard();

        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);

        $this->expectException(BadRequestHttpException::class);

        $resendGiftCardEmailAction = new ResendGiftCardEmailAction($giftCardEmailManager->reveal());
        $resendGiftCardEmailAction($giftCard);
    }
}
