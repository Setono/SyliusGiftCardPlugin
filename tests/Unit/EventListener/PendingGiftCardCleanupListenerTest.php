<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventListener\PendingGiftCardCleanupListener;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Customer;

/**
 * What the listener does to a real flush is covered by the functional PendingGiftCardCleanupTest. This pins which of
 * the scheduled deletions it acts on
 */
final class PendingGiftCardCleanupListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_removes_only_the_pending_gift_cards_of_the_units_being_deleted(): void
    {
        $pending = self::giftCardOnUnit(enabled: false);
        $enabled = self::giftCardOnUnit(enabled: true);

        $cancelled = self::giftCardOnUnit(enabled: false);
        $cancelled->addTransaction(new GiftCardTransaction());

        $unitWithoutGiftCard = new OrderItemUnit(new OrderItem());

        $unitOfWork = $this->prophesize(UnitOfWork::class);
        $unitOfWork->getScheduledEntityDeletions()->willReturn([
            new Customer(),
            $unitWithoutGiftCard,
            $pending->getOrderItemUnit(),
            $enabled->getOrderItemUnit(),
            $cancelled->getOrderItemUnit(),
            // a pending card scheduled for deletion by itself is not the listener's business either
            self::giftCardOnUnit(enabled: false),
        ]);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getUnitOfWork()->willReturn($unitOfWork->reveal());
        $entityManager->remove($pending)->shouldBeCalledOnce();
        $entityManager->remove(Argument::that(static fn (object $entity): bool => $entity !== $pending))->shouldNotBeCalled();

        (new PendingGiftCardCleanupListener())->onFlush(new OnFlushEventArgs($entityManager->reveal()));
    }

    /**
     * The listener is registered on the ORM's event manager, but Doctrine's event args are typed against any object
     * manager. Anything that is not an ORM entity manager has no unit of work to look into, and is left alone
     *
     * @test
     */
    public function it_ignores_a_flush_of_an_object_manager_that_is_not_the_orm(): void
    {
        $objectManager = $this->prophesize(ObjectManager::class);
        $objectManager->remove(Argument::any())->shouldNotBeCalled();

        // @phpstan-ignore argument.type (the ORM narrows the generic, but the constructor takes any object manager)
        (new PendingGiftCardCleanupListener())->onFlush(new OnFlushEventArgs($objectManager->reveal()));
    }

    private static function giftCardOnUnit(bool $enabled): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $enabled ? $giftCard->enable() : $giftCard->disable();

        (new OrderItemUnit(new OrderItem()))->setGiftCard($giftCard);

        return $giftCard;
    }
}
