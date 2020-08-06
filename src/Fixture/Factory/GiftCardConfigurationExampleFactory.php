<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GiftCardConfigurationExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    /** @var RepositoryInterface */
    protected $giftCardConfigurationRepository;

    /** @var GiftCardConfigurationFactoryInterface */
    protected $giftCardConfigurationFactory;

    /** @var FactoryInterface */
    private $imageFactory;

    /** @var ImageUploaderInterface */
    private $imageUploader;

    /** @var FileLocatorInterface */
    private $fileLocator;

    /** @var OptionsResolver */
    protected $optionsResolver;

    public function __construct(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationFactoryInterface $giftCardConfigurationFactory,
        FactoryInterface $imageFactory,
        ImageUploaderInterface $imageUploader,
        FileLocatorInterface $fileLocator
    ) {
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardConfigurationFactory = $giftCardConfigurationFactory;
        $this->imageFactory = $imageFactory;
        $this->imageUploader = $imageUploader;
        $this->fileLocator = $fileLocator;

        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): GiftCardConfigurationInterface
    {
        $options = $this->optionsResolver->resolve($options);

        return $this->createGiftCardConfiguration($options);
    }

    protected function createGiftCardConfiguration(array $options): GiftCardConfigurationInterface
    {
        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->findOneBy(['code' => $options['code']]);
        if (null === $giftCardConfiguration) {
            $giftCardConfiguration = $this->giftCardConfigurationFactory->createNew();
        }

        $giftCardConfiguration->setCode($options['code']);
        $giftCardConfiguration->setEnabled($options['enabled']);
        $giftCardConfiguration->setDefault($options['default']);

        $imagePath = $options['background_image'];
        /** @var string $imagePath */
        $imagePath = $this->fileLocator->locate($imagePath);
        $uploadedImage = new UploadedFile($imagePath, basename($imagePath));

        /** @var GiftCardConfigurationImageInterface $image */
        $image = $this->imageFactory->createNew();
        $image->setFile($uploadedImage);

        $this->imageUploader->upload($image);

        $giftCardConfiguration->setBackgroundImage($image);

        return $giftCardConfiguration;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', null)
            ->setAllowedTypes('code', 'string')

            ->setDefault('background_image', null)
            ->setAllowedTypes('background_image', 'string')

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')

            ->setDefault('default', false)
            ->setAllowedTypes('default', 'bool')
        ;
    }
}
