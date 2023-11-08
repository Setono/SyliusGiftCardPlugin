<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Security\GiftCardVoter;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GiftCardVoterTest extends TestCase
{
    public function testAdminCanSeeAllGiftCards(): void
    {
        $giftCard = new GiftCard();
        $voter = new GiftCardVoter();
        $user = new AdminUser();
        $token = new UsernamePasswordToken($user, 'memory');

        $this->assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($token, $giftCard, [GiftCardVoter::READ])
        );
    }

    public function testUsersCanSeeTheirGiftCards(): void
    {
        $giftCard = new GiftCard();
        $voter = new GiftCardVoter();
        $user = new ShopUser();
        $customer = new Customer();
        $user->setCustomer($customer);

        $giftCard->setCustomer($customer);

        $token = new UsernamePasswordToken($user, 'memory');

        $this->assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($token, $giftCard, [GiftCardVoter::READ])
        );
    }

    public function testUsersCanSeeAnonymousGiftCards(): void
    {
        $giftCard = new GiftCard();
        $voter = new GiftCardVoter();
        $user = new ShopUser();
        $customer = new Customer();
        $user->setCustomer($customer);

        $token = new UsernamePasswordToken($user, 'memory');

        $this->assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($token, $giftCard, [GiftCardVoter::READ])
        );
    }

    public function testUsersCanNotSeeOtherGiftCards(): void
    {
        $giftCard = new GiftCard();
        $voter = new GiftCardVoter();
        $user = new ShopUser();
        $customer1 = new Customer();
        $user->setCustomer($customer1);

        $customer2 = new Customer();
        $giftCard->setCustomer($customer2);

        $token = new UsernamePasswordToken($user, 'memory');

        $this->assertSame(
            Voter::ACCESS_DENIED,
            $voter->vote($token, $giftCard, [GiftCardVoter::READ])
        );
    }
}
