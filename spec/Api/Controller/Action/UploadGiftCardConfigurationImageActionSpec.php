<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Api\Controller\Action;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\UploadGiftCardConfigurationImageAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use SplFileInfo;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;

final class UploadGiftCardConfigurationImageActionSpec extends ObjectBehavior
{
    public function let(
        FactoryInterface $giftCardConfigurationImageFactory,
        RepositoryInterface $giftCardConfigurationImageRepository,
        ImageUploaderInterface $imageUploader,
        IriConverterInterface $iriConverter
    ): void {
        $this->beConstructedWith(
            $giftCardConfigurationImageFactory,
            $giftCardConfigurationImageRepository,
            $imageUploader,
            $iriConverter
        );
    }

    public function it_is_initialisable(): void
    {
        $this->beAnInstanceOf(UploadGiftCardConfigurationImageAction::class);
    }

    public function it_uploads_gift_card_configuration_image(
        Request $request,
        FileBag $fileBag,
        FactoryInterface $giftCardConfigurationImageFactory,
        GiftCardConfigurationImageInterface $image,
        IriConverterInterface $iriConverter,
        GiftCardConfigurationInterface $owner,
        ImageUploaderInterface $imageUploader
    ): void {
        $file = new SplFileInfo('super name');
        $fileBag->get('file')->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory->createNew()->willReturn($image);
        $image->setFile($file)->shouldBeCalled();

        $request->get('type')->willReturn('background');
        $image->setType('background')->shouldBeCalled();

        $request->get('owner')->willReturn('super-iri');
        $iriConverter->getItemFromIri('super-iri')->willReturn($owner);

        $owner->getImagesByType('background')->willReturn(new ArrayCollection());
        $owner->addImage($image)->shouldBeCalled();

        $imageUploader->upload($image)->shouldBeCalled();

        $this($request);
    }

    public function it_throws_error_if_type_is_empty(
        Request $request,
        FileBag $fileBag,
        FactoryInterface $giftCardConfigurationImageFactory,
        GiftCardConfigurationImageInterface $image
    ): void {
        $file = new SplFileInfo('super name');
        $fileBag->get('file')->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory->createNew()->willReturn($image);
        $image->setFile($file)->shouldBeCalled();

        $request->get('type')->willReturn('');

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$request]);
    }

    public function it_throws_error_if_owner_iri_is_empty(
        Request $request,
        FileBag $fileBag,
        FactoryInterface $giftCardConfigurationImageFactory,
        GiftCardConfigurationImageInterface $image
    ): void {
        $file = new SplFileInfo('super name');
        $fileBag->get('file')->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory->createNew()->willReturn($image);
        $image->setFile($file)->shouldBeCalled();

        $request->get('type')->willReturn('background');
        $image->setType('background')->shouldBeCalled();

        $request->get('owner')->willReturn('');

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$request]);
    }

    public function it_throws_error_if_owner_is_not_found(
        Request $request,
        FileBag $fileBag,
        FactoryInterface $giftCardConfigurationImageFactory,
        GiftCardConfigurationImageInterface $image,
        IriConverterInterface $iriConverter
    ): void {
        $file = new SplFileInfo('super name');
        $fileBag->get('file')->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory->createNew()->willReturn($image);
        $image->setFile($file)->shouldBeCalled();

        $request->get('type')->willReturn('background');
        $image->setType('background')->shouldBeCalled();

        $request->get('owner')->willReturn('super-iri');
        $iriConverter->getItemFromIri('super-iri')->willReturn(null);

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$request]);
    }

    public function it_deletes_old_image_of_same_type(
        Request $request,
        FileBag $fileBag,
        FactoryInterface $giftCardConfigurationImageFactory,
        GiftCardConfigurationImageInterface $image,
        IriConverterInterface $iriConverter,
        GiftCardConfigurationInterface $owner,
        ImageUploaderInterface $imageUploader,
        GiftCardConfigurationImageInterface $oldImage,
        RepositoryInterface $giftCardConfigurationImageRepository
    ): void {
        $file = new SplFileInfo('super name');
        $fileBag->get('file')->willReturn($file);
        $request->files = $fileBag;
        $giftCardConfigurationImageFactory->createNew()->willReturn($image);
        $image->setFile($file)->shouldBeCalled();

        $request->get('type')->willReturn('background');
        $image->setType('background')->shouldBeCalled();

        $request->get('owner')->willReturn('super-iri');
        $iriConverter->getItemFromIri('super-iri')->willReturn($owner);

        $owner->getImagesByType('background')->willReturn(new ArrayCollection([$oldImage->getWrappedObject()]));
        $giftCardConfigurationImageRepository->remove($oldImage->getWrappedObject())->shouldBeCalled();
        $owner->addImage($image)->shouldBeCalled();

        $imageUploader->upload($image)->shouldBeCalled();

        $this($request);
    }
}
