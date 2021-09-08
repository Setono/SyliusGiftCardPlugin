<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use ApiPlatform\Core\Api\IriConverterInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class UploadGiftCardConfigurationImageAction
{
    private FactoryInterface $giftCardConfigurationImageFactory;

    private RepositoryInterface $giftCardConfigurationImageRepository;

    private ImageUploaderInterface $imageUploader;

    private IriConverterInterface $iriConverter;

    public function __construct(
        FactoryInterface $giftCardConfigurationImageFactory,
        RepositoryInterface $giftCardConfigurationImageRepository,
        ImageUploaderInterface $imageUploader,
        IriConverterInterface $iriConverter
    ) {
        $this->giftCardConfigurationImageFactory = $giftCardConfigurationImageFactory;
        $this->giftCardConfigurationImageRepository = $giftCardConfigurationImageRepository;
        $this->imageUploader = $imageUploader;
        $this->iriConverter = $iriConverter;
    }

    public function __invoke(Request $request): GiftCardConfigurationImageInterface
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        /** @var GiftCardConfigurationImageInterface $image */
        $image = $this->giftCardConfigurationImageFactory->createNew();
        $image->setFile($file);

        /** @var string $imageType */
        $imageType = $request->get('type');
        Assert::notEmpty($imageType);

        $image->setType($imageType);

        /** @var string $ownerIri */
        $ownerIri = $request->get('owner');
        Assert::notEmpty($ownerIri);

        /** @var ResourceInterface|GiftCardConfigurationInterface $owner */
        $owner = $this->iriConverter->getItemFromIri($ownerIri);
        Assert::isInstanceOf($owner, GiftCardConfigurationInterface::class);

        $oldImages = $owner->getImagesByType($imageType);
        foreach ($oldImages as $oldImage) {
            $owner->removeImage($oldImage);
            $this->giftCardConfigurationImageRepository->remove($oldImage);
        }
        $owner->addImage($image);

        $this->imageUploader->upload($image);

        return $image;
    }
}
