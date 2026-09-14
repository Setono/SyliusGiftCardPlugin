<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use function count;
use Doctrine\ORM\OptimisticLockException;
use function in_array;
use function spl_object_id;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Turns a lost race on a gift card into a message instead of an error page.
 *
 * GiftCard carries a version column, so when two orders redeem the same card at the same moment the second
 * flush updates zero rows and Doctrine throws an OptimisticLockException. The customer did nothing wrong and
 * nothing was written — the transaction rolled back, the order was not placed and the cart still holds the
 * card — so they are told what happened and sent back to the cart summary to review it.
 *
 * The entity manager is closed once a flush fails, so nothing here may touch the database: a flash and a
 * redirect is all this can do, and all it needs to do.
 */
final class GiftCardRaceConditionSubscriber implements EventSubscriberInterface
{
    public const CHECKOUT_COMPLETE_ROUTE = 'sylius_shop_checkout_complete';

    public const CART_SUMMARY_ROUTE = 'sylius_shop_cart_summary';

    public const FLASH = 'setono_sylius_gift_card.gift_card.changed_during_checkout';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (self::CHECKOUT_COMPLETE_ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        if (!self::causedByOptimisticLock($event->getThrowable())) {
            return;
        }

        if ($request->hasSession()) {
            $session = $request->getSession();

            if ($session instanceof Session) {
                $session->getFlashBag()->add('error', self::FLASH);
            }
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::CART_SUMMARY_ROUTE)));

        // Symfony's own error listener overwrites whatever response was set before it, so the redirect only
        // survives if the event stops here
        $event->stopPropagation();
    }

    /**
     * The exception is not always the bare Doctrine one: Sylius' resource update handler rethrows it wrapped,
     * so the whole chain is inspected
     */
    private static function causedByOptimisticLock(Throwable $throwable): bool
    {
        /** @var list<int> $seen */
        $seen = [];

        for ($exception = $throwable; null !== $exception; $exception = $exception->getPrevious()) {
            if ($exception instanceof OptimisticLockException) {
                return true;
            }

            // exception chains are not guaranteed to be acyclic, and this must not hang on one that is not
            $id = spl_object_id($exception);
            if (in_array($id, $seen, true)) {
                break;
            }

            $seen[] = $id;

            if (count($seen) > 16) {
                break;
            }
        }

        return false;
    }
}
