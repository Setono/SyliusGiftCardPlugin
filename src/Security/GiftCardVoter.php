<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Security;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

final class GiftCardVoter extends Voter
{
    public const READ = 'read';

    protected function supports($attribute, $subject): bool
    {
        if (!\in_array($attribute, [self::READ], true)) {
            return false;
        }

        if (!$subject instanceof GiftCardInterface) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        /** @var UserInterface|ShopUserInterface|AdminUserInterface|null $user */
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Admin can do anything with gift cards
        if ($user instanceof AdminUserInterface) {
            return true;
        }
        Assert::isInstanceOf($user, ShopUserInterface::class);

        /** @var GiftCardInterface $giftCard */
        $giftCard = $subject;
        switch ($attribute) {
            case self::READ:
                return $this->canRead($giftCard, $user);
        }

        throw new \LogicException('This code should not be reached.');
    }

    private function canRead(GiftCardInterface $giftCard, ShopUserInterface $user): bool
    {
        // Anonymous gift cards can be seen by everyone
        if (null === $giftCard->getCustomer()) {
            return true;
        }

        // GiftCards can only be seen by their proprietary
        return $giftCard->getCustomer() === $user->getCustomer();
    }
}
