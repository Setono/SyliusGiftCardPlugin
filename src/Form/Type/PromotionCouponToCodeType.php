<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponToCodeType as SyliusPromotionCouponToCodeType;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PromotionCouponToCodeType extends AbstractType implements DataTransformerInterface
{
    /** @var SyliusPromotionCouponToCodeType */
    private $decoratedForm;

    public function __construct(SyliusPromotionCouponToCodeType $decoratedForm)
    {
        $this->decoratedForm = $decoratedForm;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->decoratedForm->buildForm($builder, $options);
    }

    public function transform($coupon): string
    {
        return $this->decoratedForm->transform($coupon);
    }

    public function reverseTransform($code): ?PromotionCouponInterface
    {
        return $this->decoratedForm->reverseTransform($code);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->decoratedForm->configureOptions($resolver);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return $this->decoratedForm->getBlockPrefix();
    }
}
