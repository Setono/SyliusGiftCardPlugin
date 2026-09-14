<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\File\File;

final class GiftCardPdfGeneratorTest extends GiftCardFunctionalTestCase
{
    /** @var list<string> Paths (relative to the media dir) of the images uploaded by a test, removed again afterwards */
    private array $uploadedImagePaths = [];

    protected function tearDown(): void
    {
        $uploader = $this->getImageUploader();
        foreach ($this->uploadedImagePaths as $path) {
            $uploader->remove($path);
        }
        $this->uploadedImagePaths = [];

        parent::tearDown();
    }

    /** @test */
    public function it_generates_a_two_page_pdf(): void
    {
        $pdf = $this->getGenerator()->generate($this->createGiftCard());

        self::assertStringStartsWith('%PDF', $pdf);

        // A gift card renders to exactly two pages: the front (artwork + amount) and the back (how-to-use + code)
        $pageObjects = preg_match_all('#/Type\s*/Page(?![s])#', $pdf);
        self::assertSame(2, $pageObjects);
    }

    /**
     * Remote resource loading is disabled in Dompdf, so the design images can only reach the PDF as local files
     * below the chrooted public dir. This guards that path: a design with a front and a back image must end up
     * with both images embedded.
     *
     * @test
     */
    public function it_embeds_the_design_images_with_remote_loading_disabled(): void
    {
        $giftCard = $this->createGiftCard();
        $giftCard->setDesign($this->createDesignWithImages());
        $this->manager->flush();

        $pdf = $this->getGenerator()->generate($giftCard);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertSame(2, preg_match_all('#/Type\s*/Page(?![s])#', $pdf));

        // Dompdf embeds every raster image it managed to load as an image XObject. An image it refused to load
        // is drawn as its vector "broken image" placeholder instead, which embeds no XObject at all.
        self::assertGreaterThanOrEqual(2, preg_match_all('#/Subtype\s*/Image#', $pdf));
    }

    private function getGenerator(): GiftCardPdfGeneratorInterface
    {
        /** @var GiftCardPdfGeneratorInterface $generator */
        $generator = self::getContainer()->get(GiftCardPdfGeneratorInterface::class);

        return $generator;
    }

    private function getImageUploader(): ImageUploaderInterface
    {
        /** @var ImageUploaderInterface $uploader */
        $uploader = self::getContainer()->get('sylius.image_uploader');

        return $uploader;
    }

    private function createGiftCard(): GiftCardInterface
    {
        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('PDFTEST0000000001');
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->setCustomMessage('Enjoy!');
        $giftCard->enable();

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    /**
     * Uploads a front and a back image through Sylius' image uploader, exactly like the admin form and the
     * fixtures do, so the images land in the public media dir the generator resolves them from
     */
    private function createDesignWithImages(): GiftCardDesignInterface
    {
        $container = self::getContainer();

        /** @var FactoryInterface<GiftCardDesignInterface> $designFactory */
        $designFactory = $container->get('setono_sylius_gift_card.factory.gift_card_design');

        /** @var FactoryInterface<GiftCardDesignImageInterface> $imageFactory */
        $imageFactory = $container->get('setono_sylius_gift_card.factory.gift_card_design_image');

        $design = $designFactory->createNew();
        $design->setCode('PDF_TEST_DESIGN');
        $design->setName('PDF test design');

        foreach ([GiftCardDesignImageInterface::TYPE_FRONT, GiftCardDesignImageInterface::TYPE_BACK] as $type) {
            $source = $this->createPng();

            $image = $imageFactory->createNew();
            $image->setType($type);
            $image->setFile(new File($source));
            $design->addImage($image);

            $this->getImageUploader()->upload($image);
            unlink($source);

            $path = $image->getPath();
            self::assertNotNull($path);
            $this->uploadedImagePaths[] = $path;
        }

        $this->manager->persist($design);

        return $design;
    }

    /**
     * Writes a small solid PNG to a temporary file and returns its path
     */
    private function createPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ssgc_pdf_');
        self::assertNotFalse($path);

        $canvas = imagecreatetruecolor(16, 16);
        self::assertNotFalse($canvas);

        $color = imagecolorallocate($canvas, 200, 40, 40);
        self::assertNotFalse($color);

        imagefilledrectangle($canvas, 0, 0, 15, 15, $color);
        self::assertTrue(imagepng($canvas, $path));

        return $path;
    }
}
