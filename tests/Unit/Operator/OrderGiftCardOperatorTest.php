<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Operator;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;

/**
 * The database side of the operator is covered by the functional OrderGiftCardOperatorTest. This covers what it
 * hands to its collaborators, and that an order without gift cards never reaches them
 */
final class OrderGiftCardOperatorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardFactoryInterface> */
    private ObjectProphecy $giftCardFactory;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    /** @var ObjectProphecy<GiftCardEmailManagerInterface> */
    private ObjectProphecy $emailManager;

    /** @var ObjectProphecy<GiftCardBalanceOperatorInterface> */
    private ObjectProphecy $balanceOperator;

    private OrderGiftCardOperator $operator;

    protected function setUp(): void
    {
        $this->giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $this->balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);

        $this->operator = new OrderGiftCardOperator(
            $this->giftCardFactory->reveal(),
            $this->managerRegistry->reveal(),
            $this->emailManager->reveal(),
            $this->balanceOperator->reveal(),
        );
    }

    /**
     * One email per order, carrying every card bought on it. Units still without a card and lines of other products
     * have nothing to send
     *
     * @test
     */
    public function it_emails_the_gift_cards_bought_on_the_order_together(): void
    {
        $first = self::giftCard('FIRST');
        $second = self::giftCard('SECOND');
        $third = self::giftCard('THIRD');

        $order = new Order();
        self::addLine($order, true, [$first, null, $second]);
        self::addLine($order, false, [null]);
        self::addLine($order, true, [$third]);

        $this->emailManager->sendGiftCardsFromOrder($order, [$first, $second, $third])->shouldBeCalledOnce();

        $this->operator->send($order);
    }

    /** @test */
    public function it_sends_nothing_when_no_gift_card_was_bought(): void
    {
        $order = new Order();
        self::addLine($order, false, [null, null]);
        self::addLine($order, true, [null]);

        $this->emailManager->sendGiftCardsFromOrder(Argument::cetera())->shouldNotBeCalled();

        $this->operator->send($order);
    }

    /**
     * Every order that completes checkout, gets paid or is cancelled passes through the operator, and most of them
     * bought no gift card. Those are left alone entirely: nothing is created, recorded or flushed. The order below
     * does not even have a channel, which reconcile would otherwise insist on
     *
     * @test
     */
    public function it_leaves_an_order_without_gift_card_lines_alone(): void
    {
        $order = new Order();
        self::addLine($order, false, [null, null]);

        $this->giftCardFactory->createForChannel(Argument::any())->shouldNotBeCalled();
        $this->balanceOperator->issue(Argument::any())->shouldNotBeCalled();
        $this->managerRegistry->getManagerForClass(Argument::any())->shouldNotBeCalled();

        $this->operator->reconcile($order);
        $this->operator->enable($order);
        $this->operator->disable($order);
    }

    /**
     * A gift card line whose units have no cards yet has nothing to enable or disable; only reconcile creates them
     *
     * @test
     */
    public function it_does_not_enable_or_disable_anything_before_the_cards_exist(): void
    {
        $order = new Order();
        self::addLine($order, true, [null]);

        $this->balanceOperator->issue(Argument::any())->shouldNotBeCalled();
        $this->managerRegistry->getManagerForClass(Argument::any())->shouldNotBeCalled();

        $this->operator->enable($order);
        $this->operator->disable($order);
    }

    /**
     * @param list<GiftCardInterface|null> $giftCards one entry per unit
     */
    private static function addLine(Order $order, bool $giftCardProduct, array $giftCards): void
    {
        $product = new Product();
        $product->setGiftCard($giftCardProduct);

        $variant = new ProductVariant();
        $variant->setProduct($product);

        $item = new OrderItem();
        $item->setVariant($variant);
        $order->addItem($item);

        foreach ($giftCards as $giftCard) {
            (new OrderItemUnit($item))->setGiftCard($giftCard);
        }
    }

    private static function giftCard(string $code): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);

        return $giftCard;
    }
}
