<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Application\Model;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\ChannelInterface as SetonoSyliusGiftCardChannelInterface;
use Setono\SyliusGiftCardPlugin\Model\ChannelTrait as SetonoSyliusGiftCardChannelTrait;
use Sylius\Component\Core\Model\Channel as BaseChannel;

/**
 * @ORM\Entity()
 * @ORM\Table(name="sylius_channel")
 */
class Channel extends BaseChannel implements SetonoSyliusGiftCardChannelInterface
{
    use SetonoSyliusGiftCardChannelTrait;
}
