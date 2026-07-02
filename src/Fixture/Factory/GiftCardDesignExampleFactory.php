<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class GiftCardDesignExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    protected \Faker\Generator $faker;

    protected OptionsResolver $optionsResolver;

    /**
     * @param FactoryInterface<GiftCardDesignInterface> $designFactory
     * @param FactoryInterface<GiftCardDesignImageInterface> $designImageFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        protected FactoryInterface $designFactory,
        protected FactoryInterface $designImageFactory,
        protected ChannelRepositoryInterface $channelRepository,
        protected ImageUploaderInterface $imageUploader,
        protected FileLocatorInterface $fileLocator,
    ) {
        $this->faker = \Faker\Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(array $options = []): GiftCardDesignInterface
    {
        $options = $this->optionsResolver->resolve($options);

        $design = $this->designFactory->createNew();

        $code = $options['code'];
        Assert::string($code);
        $design->setCode($code);

        $name = $options['name'];
        Assert::string($name);
        $design->setName($name);

        $design->setPosition((int) $options['position']);
        $design->setEnabled((bool) $options['enabled']);

        /** @var list<ChannelInterface> $channels */
        $channels = $options['channels'];
        if ([] === $channels) {
            /** @var list<ChannelInterface> $channels */
            $channels = $this->channelRepository->findAll();
        }
        foreach ($channels as $channel) {
            $design->addChannel($channel);
        }

        $this->addImage($design, $options['front_image'], GiftCardDesignImageInterface::TYPE_FRONT);
        if (null !== $options['back_image']) {
            $this->addImage($design, $options['back_image'], GiftCardDesignImageInterface::TYPE_BACK);
        }

        return $design;
    }

    private function addImage(GiftCardDesignInterface $design, mixed $path, string $type): void
    {
        Assert::string($path);

        $located = $this->fileLocator->locate($path);
        Assert::string($located);

        $image = $this->designImageFactory->createNew();
        $image->setType($type);
        $image->setFile(new File($located));

        $design->addImage($image);

        $this->imageUploader->upload($image);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', fn (Options $options): string => (string) $this->faker->unique()->slug(2))
            ->setDefault('name', function (Options $options): string {
                $words = $this->faker->words(2, true);

                return is_string($words) ? $words : implode(' ', $words);
            })
            ->setDefault('position', 0)
            ->setAllowedTypes('position', 'int')
            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')
            ->setDefault('front_image', '@SetonoSyliusGiftCardPlugin/Resources/fixtures/default_background.png')
            ->setDefault('back_image', null)
            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))
        ;
    }
}
