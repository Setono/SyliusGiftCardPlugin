<?php

declare(strict_types=1);

namespace tests\Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\UploadGiftCardConfigurationImageAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;

final class UploadGiftCardConfigurationImageActionTest extends TestCase
{
    public function testUploadGiftCardConfigurationImage(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $fileBag = $this->createMock(FileBag::class);
        $request = $this->createMock(Request::class);
        $giftCardConfigurationImageFactory = $this->createMock(FactoryInterface::class);
        $image = $this->createMock(GiftCardConfigurationImageInterface::class);
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $owner = $this->createMock(GiftCardConfigurationInterface::class);
        $imageUploader = $this->createMock(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->createMock(RepositoryInterface::class);

        $fileBag
            ->method('get')
            ->with('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->method('createNew')
            ->willReturn($image);
        $image
            ->expects($this->once())
            ->method('setFile')
            ->with($this->equalTo($file));

        $request
            ->method('get')
            ->withConsecutive(['type'], ['owner'])
            ->willReturn('background', 'super-iri');
        $image
            ->expects($this->once())
            ->method('setType')
            ->with($this->equalTo('background'));

        $iriConverter
            ->method('getItemFromIri')
            ->with('super-iri')
            ->willReturn($owner);

        $owner
            ->method('getImagesByType')
            ->with('background')
            ->willReturn(new ArrayCollection());
        $owner
            ->expects($this->once())
            ->method('addImage')
            ->with($this->equalTo($image));

        $imageUploader
            ->expects($this->once())
            ->method('upload')
            ->with($this->equalTo($image));

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory,
            $giftCardConfigurationImageRepository,
            $imageUploader,
            $iriConverter
        );
        $uploadGiftCardConfigurationImageAction($request);
    }

    public function testThrowErrorIfImageEmpty(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $fileBag = $this->createMock(FileBag::class);
        $request = $this->createMock(Request::class);
        $giftCardConfigurationImageFactory = $this->createMock(FactoryInterface::class);
        $image = $this->createMock(GiftCardConfigurationImageInterface::class);
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $imageUploader = $this->createMock(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->createMock(RepositoryInterface::class);

        $fileBag
            ->method('get')
            ->with('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->method('createNew')
            ->willReturn($image);
        $image
            ->expects($this->once())
            ->method('setFile')
            ->with($this->equalTo($file));

        $request
            ->method('get')
            ->with('type')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory,
            $giftCardConfigurationImageRepository,
            $imageUploader,
            $iriConverter
        );
        $uploadGiftCardConfigurationImageAction($request);
    }

    public function testThrowErrorIfOwnerIriEmpty(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $fileBag = $this->createMock(FileBag::class);
        $request = $this->createMock(Request::class);
        $giftCardConfigurationImageFactory = $this->createMock(FactoryInterface::class);
        $image = $this->createMock(GiftCardConfigurationImageInterface::class);
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $imageUploader = $this->createMock(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->createMock(RepositoryInterface::class);

        $fileBag
            ->method('get')
            ->with('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->method('createNew')
            ->willReturn($image);
        $image
            ->expects($this->once())
            ->method('setFile')
            ->with($this->equalTo($file));

        $request
            ->method('get')
            ->withConsecutive(['type'], ['owner'])
            ->willReturn('background', null);
        $image
            ->expects($this->once())
            ->method('setType')
            ->with($this->equalTo('background'));

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory,
            $giftCardConfigurationImageRepository,
            $imageUploader,
            $iriConverter
        );
        $uploadGiftCardConfigurationImageAction($request);
    }

    public function testThrowErrorIfOwnerNotFound(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $fileBag = $this->createMock(FileBag::class);
        $request = $this->createMock(Request::class);
        $giftCardConfigurationImageFactory = $this->createMock(FactoryInterface::class);
        $image = $this->createMock(GiftCardConfigurationImageInterface::class);
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $imageUploader = $this->createMock(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->createMock(RepositoryInterface::class);

        $fileBag
            ->method('get')
            ->with('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->method('createNew')
            ->willReturn($image);
        $image
            ->expects($this->once())
            ->method('setFile')
            ->with($this->equalTo($file));

        $request
            ->method('get')
            ->withConsecutive(['type'], ['owner'])
            ->willReturn('background', 'super-iri');
        $image
            ->expects($this->once())
            ->method('setType')
            ->with($this->equalTo('background'));

        $iriConverter
            ->method('getItemFromIri')
            ->with('super-iri')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory,
            $giftCardConfigurationImageRepository,
            $imageUploader,
            $iriConverter
        );
        $uploadGiftCardConfigurationImageAction($request);
    }

    public function testDeleteOldImageOfSameType(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $fileBag = $this->createMock(FileBag::class);
        $request = $this->createMock(Request::class);
        $giftCardConfigurationImageFactory = $this->createMock(FactoryInterface::class);
        $image = $this->createMock(GiftCardConfigurationImageInterface::class);
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $owner = $this->createMock(GiftCardConfigurationInterface::class);
        $imageUploader = $this->createMock(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->createMock(RepositoryInterface::class);
        $oldImage = $this->createMock(GiftCardConfigurationImageInterface::class);

        $fileBag
            ->method('get')
            ->with('file')
            ->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory
            ->method('createNew')
            ->willReturn($image);
        $image
            ->expects($this->once())
            ->method('setFile')
            ->with($this->equalTo($file));

        $request
            ->method('get')
            ->withConsecutive(['type'], ['owner'])
            ->willReturn('background', 'super-iri');
        $image
            ->expects($this->once())
            ->method('setType')
            ->with($this->equalTo('background'));

        $iriConverter
            ->method('getItemFromIri')
            ->with('super-iri')
            ->willReturn($owner);

        $owner
            ->method('getImagesByType')
            ->with('background')
            ->willReturn(new ArrayCollection([$oldImage]));

        $giftCardConfigurationImageRepository
            ->expects($this->once())
            ->method('remove')
            ->with($this->equalTo($oldImage));
        $owner
            ->expects($this->once())
            ->method('addImage')
            ->with($this->equalTo($image));

        $imageUploader
            ->expects($this->once())
            ->method('upload')
            ->with($this->equalTo($image));

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory,
            $giftCardConfigurationImageRepository,
            $imageUploader,
            $iriConverter
        );
        $uploadGiftCardConfigurationImageAction($request);
    }
}
