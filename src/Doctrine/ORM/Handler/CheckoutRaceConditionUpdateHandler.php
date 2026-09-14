<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM\Handler;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardRaceConditionSubscriber;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Resource\Exception\RaceConditionException;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lets a lost race at checkout completion reach GiftCardRaceConditionSubscriber.
 *
 * Sylius wraps the OptimisticLockException raised by the flush in a RaceConditionException, which the resource
 * controller swallows: it adds an error flash and redirects to the referer. The shop's checkout complete route
 * sets `flash: false`, so no flash is added either, and the customer silently lands back on the checkout page
 * with no idea why the order was not placed.
 *
 * Only that one route is affected — everywhere else, and for any other update handling failure, Sylius' own
 * behaviour is left untouched.
 */
final class CheckoutRaceConditionUpdateHandler implements ResourceUpdateHandlerInterface
{
    public function __construct(
        private readonly ResourceUpdateHandlerInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function handle(
        ResourceInterface $resource,
        RequestConfiguration $requestConfiguration,
        ObjectManager $manager,
    ): void {
        try {
            $this->decorated->handle($resource, $requestConfiguration, $manager);
        } catch (RaceConditionException $e) {
            $previous = $e->getPrevious();

            if (!$previous instanceof OptimisticLockException || !$this->isCheckoutCompletion()) {
                throw $e;
            }

            throw $previous;
        }
    }

    private function isCheckoutCompletion(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request &&
            GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE === $request->attributes->get('_route');
    }
}
