<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductTypeExtension extends AbstractTypeExtension
{
    /** @var EntityManagerInterface */
    private $giftCardManager;

    /** @var GiftCardFactoryInterface */
    private $giftCardFactory;

    public function __construct(EntityManagerInterface $giftCardManager, GiftCardFactoryInterface $giftCardFactory)
    {
        $this->giftCardManager = $giftCardManager;
        $this->giftCardFactory = $giftCardFactory;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (false === $options['is_gift_card']) {
            return;
        }

        /** @var ProductInterface $product */
        $product = $builder->getData();
        $product->getVariants()->first()->setShippingRequired(false);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function () use ($product): void {
            if (null !== $product->getId()) {
                return;
            }

            $giftCard = $this->giftCardFactory->createWithProduct($product);

            $this->giftCardManager->persist($giftCard);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('is_gift_card', false)
            ->setAllowedTypes('is_gift_card', 'bool')
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            ProductType::class,
        ];
    }
}
