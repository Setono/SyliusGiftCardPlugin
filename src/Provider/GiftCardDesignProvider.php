<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\File\File;

final class GiftCardDesignProvider implements GiftCardDesignProviderInterface
{
    use ORMTrait;

    private const DEFAULT_DESIGN_CODE = 'classic';

    /**
     * @param FactoryInterface<GiftCardDesignInterface> $designFactory
     * @param FactoryInterface<GiftCardDesignImageInterface> $designImageFactory
     */
    public function __construct(private readonly GiftCardDesignRepositoryInterface $designRepository, private readonly FactoryInterface $designFactory, private readonly FactoryInterface $designImageFactory, private readonly ImageUploaderInterface $imageUploader, ManagerRegistry $managerRegistry, private readonly string $defaultImagePath, private readonly LoggerInterface $logger = new NullLogger())
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function getDesigns(ChannelInterface $channel): array
    {
        $designs = $this->designRepository->findEnabledByChannel($channel);
        if ([] !== $designs) {
            return $designs;
        }

        $design = $this->createDefaultDesign($channel);
        if (null === $design) {
            return $this->designRepository->findEnabledByChannel($channel);
        }

        return [$design];
    }

    private function createDefaultDesign(ChannelInterface $channel): ?GiftCardDesignInterface
    {
        $design = $this->designFactory->createNew();
        $design->setCode(self::DEFAULT_DESIGN_CODE);
        $design->setName('Classic');
        $design->setEnabled(true);
        $design->setPosition(0);
        $design->addChannel($channel);

        $image = $this->designImageFactory->createNew();
        $image->setType(GiftCardDesignImageInterface::TYPE_FRONT);
        $image->setFile($this->createTemporaryImageFile());
        $design->addImage($image);

        try {
            $this->imageUploader->upload($image);

            $manager = $this->getManager($design);
            $manager->persist($design);
            $manager->flush();

            $this->logger->info(sprintf(
                'Created a default "%s" gift card design for channel "%s" because it had no enabled designs',
                self::DEFAULT_DESIGN_CODE,
                (string) $channel->getCode(),
            ));

            return $design;
        } catch (ORMException $e) {
            // Most likely a concurrent request already created the default design; fall back to re-querying
            $this->logger->warning(sprintf(
                'Could not create the default gift card design for channel "%s": %s',
                (string) $channel->getCode(),
                $e->getMessage(),
            ));

            return null;
        }
    }

    private function createTemporaryImageFile(): File
    {
        $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'ssgc_design_');
        copy($this->defaultImagePath, $temporaryPath);

        return new File($temporaryPath);
    }
}
