<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Doctrine\QueryCollectionExtension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Doctrine\QueryCollectionExtension\GiftCardsByLoggedInUserExtension;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ApiBundle\Context\UserContextInterface;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class GiftCardsByLoggedInUserExtensionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_applies_filter_to_gift_cards_collection(): void
    {
        $shopUser = new ShopUser();
        $customer = new Customer();
        $shopUser->setCustomer($customer);

        $userContext = $this->prophesize(UserContextInterface::class);
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryNameGenerator = $this->prophesize(QueryNameGeneratorInterface::class);

        $userContext
            ->getUser()
            ->willReturn($shopUser);
        $queryBuilder
            ->getRootAliases()
            ->willReturn([0 => 'root_alias']);

        $queryBuilder
            ->andWhere('root_alias.customer = :customer')
            ->willReturn($queryBuilder->reveal());

        $queryBuilder
            ->setParameter('customer', $customer->getId(), Types::INTEGER)
            ->shouldBeCalled();

        $giftCardsByLoggedInUserExtension = new GiftCardsByLoggedInUserExtension($userContext->reveal());
        $giftCardsByLoggedInUserExtension->applyToCollection(
            $queryBuilder->reveal(),
            $queryNameGenerator->reveal(),
            GiftCardInterface::class,
        );
    }

    /**
     * @test
     */
    public function it_does_not_apply_filter_for_other_collections(): void
    {
        $shopUser = new ShopUser();
        $customer = new Customer();
        $shopUser->setCustomer($customer);

        $userContext = $this->prophesize(UserContextInterface::class);
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryNameGenerator = $this->prophesize(QueryNameGeneratorInterface::class);

        $userContext
            ->getUser()
            ->shouldNotBeCalled();
        $queryBuilder
            ->andWhere('root_alias.customer = :customer')
            ->shouldNotBeCalled();

        $giftCardsByLoggedInUserExtension = new GiftCardsByLoggedInUserExtension($userContext->reveal());
        $giftCardsByLoggedInUserExtension->applyToCollection(
            $queryBuilder->reveal(),
            $queryNameGenerator->reveal(),
            ShopUser::class,
        );
    }

    /**
     * @test
     */
    public function it_does_not_apply_filter_if_user_is_admin(): void
    {
        $user = new AdminUser();

        $userContext = $this->prophesize(UserContextInterface::class);
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryNameGenerator = $this->prophesize(QueryNameGeneratorInterface::class);

        $userContext
            ->getUser()
            ->willReturn($user);
        $queryBuilder
            ->getRootAliases()
            ->shouldNotBeCalled();

        $queryBuilder
            ->andWhere('root_alias.customer = :customer')
            ->shouldNotBeCalled();

        $giftCardsByLoggedInUserExtension = new GiftCardsByLoggedInUserExtension($userContext->reveal());
        $giftCardsByLoggedInUserExtension->applyToCollection(
            $queryBuilder->reveal(),
            $queryNameGenerator->reveal(),
            GiftCardInterface::class,
        );
    }

    /**
     * @test
     */
    public function it_throws_exception_if_user_is_logged_out(): void
    {
        $userContext = $this->prophesize(UserContextInterface::class);
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryNameGenerator = $this->prophesize(QueryNameGeneratorInterface::class);

        $userContext
            ->getUser()
            ->willReturn(null);
        $queryBuilder
            ->getRootAliases()
            ->shouldNotBeCalled();

        $queryBuilder
            ->andWhere('root_alias.customer = :customer')
            ->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);
        $giftCardsByLoggedInUserExtension = new GiftCardsByLoggedInUserExtension($userContext->reveal());
        $giftCardsByLoggedInUserExtension->applyToCollection(
            $queryBuilder->reveal(),
            $queryNameGenerator->reveal(),
            GiftCardInterface::class,
        );
    }
}
