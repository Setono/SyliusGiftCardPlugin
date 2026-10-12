<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Command;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardPaymentMethodFactoryInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the payment method gift card payments are made with.
 *
 * This used to happen lazily the first time a customer paid with a gift card, flushing everything else pending in the
 * middle of placing the order. Creating the method is now an explicit, idempotent setup step: when the method exists,
 * whether this command or an admin created it, it is left as it is.
 */
#[AsCommand(
    name: 'setono:gift-card:create-payment-method',
    description: 'Creates the payment method gift card payments are made with',
)]
final class CreatePaymentMethodCommand extends Command
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardPaymentMethodProviderInterface $paymentMethodProvider,
        private readonly GiftCardPaymentMethodFactoryInterface $paymentMethodFactory,
        ManagerRegistry $managerRegistry,
    ) {
        parent::__construct();

        $this->managerRegistry = $managerRegistry;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $paymentMethod = $this->paymentMethodProvider->findPaymentMethod();
        if (null !== $paymentMethod) {
            $io->text(sprintf('The gift card payment method "%s" already exists', (string) $paymentMethod->getCode()));

            // An administrator may have disabled it on purpose, to stop gift cards being redeemed for a while, so a deploy
            // running this leaves it disabled and only says so
            if (!$paymentMethod->isEnabled()) {
                $io->warning(sprintf(
                    'The gift card payment method "%s" is disabled, so the shop refuses gift cards until it is enabled again in the admin',
                    (string) $paymentMethod->getCode(),
                ));
            }

            return Command::SUCCESS;
        }

        $paymentMethod = $this->paymentMethodFactory->create();

        $manager = $this->getManager($paymentMethod);
        $manager->persist($paymentMethod);
        $manager->flush();

        $io->text(sprintf('Created the gift card payment method "%s"', (string) $paymentMethod->getCode()));

        return Command::SUCCESS;
    }
}
