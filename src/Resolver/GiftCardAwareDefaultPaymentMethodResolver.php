<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Exception\UnresolvedDefaultPaymentMethodException;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface as BasePaymentMethodInterface;
use Sylius\Component\Payment\Resolver\DefaultPaymentMethodResolverInterface;
use Webmozart\Assert\Assert;

/**
 * Ensures the gift card payment method is never returned as the default method for a (gateway) payment
 */
final class GiftCardAwareDefaultPaymentMethodResolver implements DefaultPaymentMethodResolverInterface
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private readonly DefaultPaymentMethodResolverInterface $decorated,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly string $paymentMethodCode,
    ) {
    }

    public function getDefaultPaymentMethod(BasePaymentInterface $payment): BasePaymentMethodInterface
    {
        $method = $this->decorated->getDefaultPaymentMethod($payment);
        if ($method->getCode() !== $this->paymentMethodCode) {
            return $method;
        }

        if ($payment instanceof PaymentInterface) {
            $channel = $payment->getOrder()?->getChannel();
            if ($channel instanceof ChannelInterface) {
                foreach ($this->paymentMethodRepository->findEnabledForChannel($channel) as $candidate) {
                    Assert::isInstanceOf($candidate, PaymentMethodInterface::class);

                    if ($candidate->getCode() !== $this->paymentMethodCode) {
                        return $candidate;
                    }
                }
            }
        }

        throw new UnresolvedDefaultPaymentMethodException();
    }
}
