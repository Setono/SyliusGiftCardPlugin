<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductTypeExtension extends AbstractTypeExtension
{
    /** @var EntityManagerInterface */
    private $giftCardEntityManager;

    /** @var GiftCardFactoryInterface */
    private $giftCardFactory;

    public function __construct(EntityManagerInterface $giftCardEntityManager, GiftCardFactoryInterface $giftCardFactory)
    {
        $this->giftCardEntityManager = $giftCardEntityManager;
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

            $this->giftCardEntityManager->persist($giftCard);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('is_gift_card', false);
    }

    public function getExtendedType(): string
    {
        return ProductType::class;
    }
}
