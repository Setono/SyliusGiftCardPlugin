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
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class UploadGiftCardConfigurationImageActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_uploads_gift_card_configuration_image(): void
    {
        $file = new UploadedFile(__DIR__ . '/file.jpg', 'file.jpg');
        $image = new GiftCardConfigurationImage();
        $owner = new GiftCardConfiguration();

        $request = new Request([], [
            'type' => 'background',
            'owner' => 'super-iri',
        ], [], [], [
            'file' => $file,
        ]);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $iriConverter
            ->getItemFromIri('super-iri')
            ->willReturn($owner);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $returnedImage = $uploadGiftCardConfigurationImageAction($request);

        self::assertSame($owner, $returnedImage->getOwner());
        self::assertSame('background', $returnedImage->getType());
        self::assertSame($file, $returnedImage->getFile());
    }

    /**
     * @test
     */
    public function it_throws_error_if_image_empty(): void
    {
        $image = new GiftCardConfigurationImage();

        $request = new Request([], [
            'type' => null,
            'owner' => 'super-iri',
        ]);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $uploadGiftCardConfigurationImageAction($request);
    }

    /**
     * @test
     */
    public function it_throws_error_if_owner_iri_empty(): void
    {
        $file = new UploadedFile(__DIR__ . '/file.jpg', 'file.jpg');
        $image = new GiftCardConfigurationImage();

        $request = new Request([], [
            'type' => 'background',
            'owner' => 'super-iri',
        ], [], [], [
            'file' => $file,
        ]);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

        $this->expectException(InvalidArgumentException::class);

        $uploadGiftCardConfigurationImageAction = new UploadGiftCardConfigurationImageAction(
            $giftCardConfigurationImageFactory->reveal(),
            $giftCardConfigurationImageRepository->reveal(),
            $imageUploader->reveal(),
            $iriConverter->reveal()
        );
        $uploadGiftCardConfigurationImageAction($request);
    }

    /**
     * @test
     */
    public function it_throws_error_if_owner_not_found(): void
    {
        $file = new UploadedFile(__DIR__ . '/file.jpg', 'file.jpg');
        $image = new GiftCardConfigurationImage();

        $request = new Request([], [
            'type' => 'background',
            'owner' => 'super-iri',
        ], [], [], [
            'file' => $file,
        ]);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

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
        $uploadGiftCardConfigurationImageAction($request);
    }

    /**
     * @test
     */
    public function it_deletes_old_image_of_same_type(): void
    {
        $file = new UploadedFile(__DIR__ . '/file.jpg', 'file.jpg');
        $image = new GiftCardConfigurationImage();
        $oldImage = new GiftCardConfigurationImage();
        $oldImage->setType('background');
        $owner = new GiftCardConfiguration();

        $owner->addImage($oldImage);

        $request = new Request([], [
            'type' => 'background',
            'owner' => 'super-iri',
        ], [], [], [
            'file' => $file,
        ]);
        $giftCardConfigurationImageFactory = $this->prophesize(FactoryInterface::class);
        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $imageUploader = $this->prophesize(ImageUploaderInterface::class);
        $giftCardConfigurationImageRepository = $this->prophesize(RepositoryInterface::class);

        $giftCardConfigurationImageFactory
            ->createNew()
            ->willReturn($image);

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
        $returnedImage = $uploadGiftCardConfigurationImageAction($request);

        self::assertEquals(1, $owner->getImagesByType('background')->count());
        $image = $owner->getImagesByType('background')->first();
        self::assertEquals($returnedImage, $image);
    }
}
