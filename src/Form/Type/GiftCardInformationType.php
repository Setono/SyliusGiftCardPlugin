<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmount;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class GiftCardInformationType extends AbstractType
{
    private const VALIDATION_GROUPS = ['setono_sylius_gift_card'];

    private const MAX_MESSAGE_LENGTH = 500;

    public function __construct(
        private readonly string $dataClass,
        private readonly string $designClass,
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardDesignProviderInterface $designProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        $currencyCode = (string) $channel->getBaseCurrency()?->getCode();

        $designs = $this->designProvider->getDesigns($channel);

        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_information.amount',
                'currency' => $currencyCode,
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                    new ValidGiftCardAmount(['groups' => self::VALIDATION_GROUPS]),
                ],
            ])
            ->add('customMessage', TextareaType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_information.custom_message',
                'required' => false,
                'attr' => [
                    'maxlength' => self::MAX_MESSAGE_LENGTH,
                    'placeholder' => 'setono_sylius_gift_card.form.gift_card_information.custom_message_placeholder',
                ],
                'constraints' => [
                    new Length(['max' => self::MAX_MESSAGE_LENGTH, 'groups' => self::VALIDATION_GROUPS]),
                ],
            ])
            ->add('design', EntityType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_information.design',
                'class' => $this->designClass,
                'choices' => $designs,
                'choice_label' => 'name',
                'choice_value' => 'code',
                'expanded' => true,
                'multiple' => false,
                'data' => $designs[0] ?? null,
                'choice_attr' => static function (?GiftCardDesignInterface $design): array {
                    $front = $design?->getFrontImage();

                    return ['data-image-path' => $front?->getPath() ?? ''];
                },
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->dataClass,
            'validation_groups' => self::VALIDATION_GROUPS,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_information';
    }
}
