<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Controller\Action;

use ApiPlatform\Core\Api\IriConverterInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\UploadGiftCardConfigurationImageAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImage;
use SplFileInfo;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;

final class UploadGiftCardConfigurationImageActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_uploads_gift_card_configuration_image(): void
    {
        $file = new SplFileInfo('file');
        $image = new GiftCardConfigurationImage();
        $owner = new GiftCardConfiguration();

        $fileBag = $this->prophesize(FileBag::class);
        $request = $this->prophesize(Request::class);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $fileBag
            ->get('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $request
            ->get('type')
            ->willReturn('background');
        $request
            ->get('owner')
            ->willReturn('super-iri');

        $iriConverter
            ->getItemFromIri('super-iri')
            ->willReturn($owner);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $returnedImage = $uploadGiftCardConfigurationImageAction($request->reveal());

        self::assertEquals($returnedImage->getOwner(), $owner);
        self::assertEquals($returnedImage->getType(), 'background');
        self::assertEquals($returnedImage->getFile(), $file);
    }

    /**
     * @test
     */
    public function it_throws_error_if_image_empty(): void
    {
        $file = new SplFileInfo('file');
        $image = new GiftCardConfigurationImage();

        $fileBag = $this->prophesize(FileBag::class);
        $request = $this->prophesize(Request::class);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $fileBag
            ->get('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $request
            ->get('type')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $uploadGiftCardConfigurationImageAction($request->reveal());
    }

    /**
     * @test
     */
    public function it_throws_error_if_owner_iri_empty(): void
    {
        $file = new SplFileInfo('file');
        $image = new GiftCardConfigurationImage();

        $fileBag = $this->prophesize(FileBag::class);
        $request = $this->prophesize(Request::class);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $fileBag
            ->get('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $request
            ->get('type')
            ->willReturn('background');
        $request
            ->get('owner')
            ->willReturn('super-iri');

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $uploadGiftCardConfigurationImageAction($request->reveal());
    }

    /**
     * @test
     */
    public function it_throws_error_if_owner_not_found(): void
    {
        $file = new SplFileInfo('file');
        $image = new GiftCardConfigurationImage();

        $fileBag = $this->prophesize(FileBag::class);
        $request = $this->prophesize(Request::class);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $fileBag
            ->get('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $request
            ->get('type')
            ->willReturn('background');
        $request
            ->get('owner')
            ->willReturn('super-iri');

        $iriConverter
            ->getItemFromIri('super-iri')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $uploadGiftCardConfigurationImageAction($request->reveal());
    }

    /**
     * @test
     */
    public function it_deletes_old_image_of_same_type(): void
    {
        $file = new SplFileInfo('file');
        $image = new GiftCardConfigurationImage();
        $oldImage = new GiftCardConfigurationImage();
        $oldImage->setType('background');
        $owner = new GiftCardConfiguration();

        $owner->addImage($oldImage);

        $fileBag = $this->prophesize(FileBag::class);
        $request = $this->prophesize(Request::class);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $fileBag
            ->get('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $request
            ->get('type')
            ->willReturn('background');
        $request
            ->get('owner')
            ->willReturn('super-iri');

        $iriConverter
            ->getItemFromIri('super-iri')
            ->willReturn($owner);

        $giftCardConfigurationImageRepository->remove($oldImage)->shouldBeCalled();

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $returnedImage = $uploadGiftCardConfigurationImageAction($request->reveal());

        self::assertEquals(1, $owner->getImagesByType('background')->count());
        $image = $owner->getImagesByType('background')->first();
        self::assertEquals($returnedImage, $image);
    }
}
