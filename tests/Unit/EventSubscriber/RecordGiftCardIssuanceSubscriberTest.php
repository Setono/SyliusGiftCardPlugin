<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventSubscriber\RecordGiftCardIssuanceSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * A card created in the admin holds its balance from the moment it is saved, so its opening balance goes into the
 * ledger right away. It is recorded before the resource controller saves the card, so the controller's flush writes
 * the card and its ledger row together, and the subscriber flushes nothing itself. The row names the administrator
 * who issued the card
 */
final class RecordGiftCardIssuanceSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_runs_last_before_a_gift_card_is_created(): void
    {
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.pre_create' => ['recordIssuance', -1000]],
            RecordGiftCardIssuanceSubscriber::getSubscribedEvents(),
        );
    }

    /**
     * No order paid for a card issued in the admin, and the administrator who issued it is named by their user
     * identifier
     *
     * @test
     */
    public function it_records_the_issuance_of_the_created_gift_card_by_the_signed_in_administrator(): void
    {
        $giftCard = new GiftCard();

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('jane', null), 'admin', ['ROLE_ADMINISTRATION_ACCESS']));

        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($giftCard, null, 'jane')->shouldBeCalledOnce();

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal(), $tokenStorage);
        $subscriber->recordIssuance(new ResourceControllerEvent($giftCard));
    }

    /**
     * @test
     *
     * @dataProvider provideTokensOfNobody
     */
    public function it_records_the_issuance_naming_nobody_when_nobody_is_signed_in(?TokenInterface $token): void
    {
        $giftCard = new GiftCard();

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($giftCard, null, null)->shouldBeCalledOnce();

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal(), $tokenStorage);
        $subscriber->recordIssuance(new ResourceControllerEvent($giftCard));
    }

    /**
     * @return iterable<string, array{TokenInterface|null}>
     */
    public static function provideTokensOfNobody(): iterable
    {
        yield 'no token' => [null];
        yield 'a token without a user' => [new NullToken()];
    }

    /** @test */
    public function it_ignores_anything_but_a_gift_card(): void
    {
        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue(Argument::cetera())->shouldNotBeCalled();

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal(), new TokenStorage());
        $subscriber->recordIssuance(new ResourceControllerEvent(new Customer()));
    }
}
