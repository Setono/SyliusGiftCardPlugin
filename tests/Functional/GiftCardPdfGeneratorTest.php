<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\DompdfGiftCardPdfGenerator;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

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
     * The grouping used to be hardcoded 4-4-4-4 slicing, which printed a code an admin typed, such as SUMMER26,
     * as SUMM-ER26--. The PDF's HTML is asserted rather than the PDF bytes, whose text streams are compressed
     *
     * @test
     */
    public function it_groups_the_code_however_long_it_is(): void
    {
        $html = $this->renderPdfHtml($this->createGiftCard('SUMMER26'), 'en_US');

        self::assertStringContainsString('SUMM-ER26', $html);
        self::assertStringNotContainsString('SUMM-ER26-', $html);
    }

    /**
     * The PDF is rendered outside of a request - on order confirmation, or when an admin downloads it - so the
     * translator's current locale is the admin's UI locale or the CLI default, never the customer's. Every
     * label has to be translated in the locale of the gift card instead
     *
     * @test
     */
    public function it_translates_the_pdf_in_the_gift_cards_locale_not_the_current_one(): void
    {
        /** @var LocaleAwareInterface $translator */
        $translator = self::getContainer()->get('translator');
        $translator->setLocale('en_US');

        $giftCard = $this->createGiftCard();
        $giftCard->setExpiresAt(new \DateTime('2030-12-31'));

        $html = $this->renderPdfHtml($giftCard, 'da_DK');

        self::assertStringContainsString('lang="da-DK"', $html);

        // from src/Resources/translations/messages.da.yml. The first two come from the shared _card.html.twig
        // partial, which is included without context and therefore has to be handed the locale explicitly
        self::assertStringContainsString('VÆRDI', $html);
        self::assertStringContainsString('Gavekort', $html);
        self::assertStringContainsString('SÅDAN INDLØSER DU', $html);
        self::assertStringContainsString('Gyldigt til', $html);

        self::assertStringNotContainsString('VALUE', $html);
        self::assertStringNotContainsString('HOW TO REDEEM', $html);
    }

    /** @test */
    public function it_localizes_the_expiry_date(): void
    {
        $giftCard = $this->createGiftCard();
        $giftCard->setExpiresAt(new \DateTime('2030-12-31'));

        $danish = $this->renderPdfHtml($giftCard, 'da_DK');
        $english = $this->renderPdfHtml($giftCard, 'en_US');

        // the date used to be printed as a raw Y-m-d string, the same in every language
        self::assertStringNotContainsString('2030-12-31', $danish);

        self::assertSame(1, preg_match('#Gyldigt til\s+(.+?)\.\s*</div>#us', $danish, $danishMatch));
        self::assertSame(1, preg_match('#Valid until\s+(.+?)\.\s*</div>#us', $english, $englishMatch));
        self::assertNotSame($englishMatch[1], $danishMatch[1]);
    }

    /**
     * A design's back image used to be the whole back: no code, no redemption copy, no terms. The one thing the
     * back of a gift card is for cannot depend on whether the merchant uploaded artwork
     *
     * @test
     */
    public function it_overlays_the_code_on_a_back_image(): void
    {
        $giftCard = $this->createGiftCard('SUMMER26');

        $withImage = $this->renderPdfHtml($giftCard, 'en_US', '/tmp/back-artwork.png');
        $withoutImage = $this->renderPdfHtml($giftCard, 'en_US');

        self::assertStringContainsString('/tmp/back-artwork.png', $withImage);
        self::assertStringNotContainsString('/tmp/back-artwork.png', $withoutImage);

        // the panel the copy is overlaid on, and the copy itself, which only the image variant used to lack
        self::assertStringContainsString('class="back-panel"', $withImage);
        foreach (['SUMM-ER26', 'HOW TO REDEEM', 'REDEMPTION CODE'] as $expected) {
            self::assertStringContainsString($expected, $withImage);
            self::assertStringContainsString($expected, $withoutImage);
        }
    }

    /**
     * Nothing in the plugin redeems a gift card anywhere but in the shop's own checkout, where it becomes a
     * payment on the order, so the back may not promise that a shop assistant can scan the card
     *
     * @test
     */
    public function it_directs_the_customer_to_the_online_checkout(): void
    {
        $channel = $this->getChannel();
        $channel->setHostname('gifts.example.com');
        $this->manager->flush();

        $html = $this->renderPdfHtml($this->createGiftCard(), 'en_US');

        self::assertStringContainsString('Enter the code at checkout on gifts.example.com', $html);
        self::assertStringNotContainsString('in store', $html);
    }

    /**
     * The card is laid out on a fixed pixel grid that is A6 landscape. Told to render on anything bigger it used
     * to draw that grid at its literal size into a corner of a mostly empty sheet; it now scales to the page
     *
     * @test
     */
    public function it_scales_the_card_onto_the_configured_page_size(): void
    {
        $giftCard = $this->createGiftCard();

        $a6 = $this->generatePdf($giftCard, 'A6');
        $a4 = $this->generatePdf($giftCard, 'A4');

        self::assertStringContainsString('/MediaBox [0.000 0.000 419.530 297.640]', $a6);
        self::assertStringContainsString('/MediaBox [0.000 0.000 841.890 595.280]', $a4);

        // the layout's 42px gutter on its 560px wide grid, i.e. 7.5% of the page - on both page sizes
        self::assertEqualsWithDelta(0.075, $this->leftmostTextOffset($a6) / 419.53, 0.005);
        self::assertEqualsWithDelta(0.075, $this->leftmostTextOffset($a4) / 841.89, 0.005);
    }

    /**
     * The horizontal offset in points of the leftmost piece of text in the PDF, as it lands on the page. Text
     * positions are the only part of the layout that survives into the content stream in a form that is cheap to
     * read back, but the card is drawn under a transformation, so the operators that move things about are
     * followed: q and Q push and pop the graphics state, cm multiplies the current transformation matrix and the
     * first Td after BT places the text. What is measured is where the text ends up, however it got there
     */
    private function leftmostTextOffset(string $pdf): float
    {
        /** @var list<float> $offsets */
        $offsets = [];

        preg_match_all('#stream(.*?)endstream#s', $pdf, $streams);
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress(ltrim($stream, "\r\n"));
            if (!is_string($content)) {
                continue;
            }

            $identity = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
            $matrix = $identity;
            $stack = [];

            preg_match_all(
                '#(?:^|\s)(q|Q)(?=\s)|(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) cm|BT (-?[\d.]+) (-?[\d.]+) Td#',
                $content,
                $operators,
                \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL,
            );

            foreach ($operators as $operator) {
                $state = $operator[1] ?? null;
                $cm = $operator[2] ?? null;
                $x = $operator[8] ?? null;
                $y = $operator[9] ?? null;

                if ('q' === $state) {
                    $stack[] = $matrix;
                } elseif ('Q' === $state) {
                    $matrix = array_pop($stack) ?? $identity;
                } elseif (null !== $cm) {
                    $matrix = self::multiply([
                        (float) $cm,
                        (float) ($operator[3] ?? 0),
                        (float) ($operator[4] ?? 0),
                        (float) ($operator[5] ?? 0),
                        (float) ($operator[6] ?? 0),
                        (float) ($operator[7] ?? 0),
                    ], $matrix);
                } elseif (null !== $x && null !== $y) {
                    $offsets[] = $matrix[0] * (float) $x + $matrix[2] * (float) $y + $matrix[4];
                }
            }
        }

        self::assertNotEmpty($offsets, 'The PDF contains no text to measure the layout by');

        return min($offsets);
    }

    /**
     * The product of two PDF transformation matrices [a b c d e f], the way cm concatenates them
     *
     * @param list<float> $left
     * @param list<float> $right
     *
     * @return list<float>
     */
    private static function multiply(array $left, array $right): array
    {
        [$a1, $b1, $c1, $d1, $e1, $f1] = $left;
        [$a2, $b2, $c2, $d2, $e2, $f2] = $right;

        return [
            $a1 * $a2 + $b1 * $c2,
            $a1 * $b2 + $b1 * $d2,
            $c1 * $a2 + $d1 * $c2,
            $c1 * $b2 + $d1 * $d2,
            $e1 * $a2 + $f1 * $c2 + $e2,
            $e1 * $b2 + $f1 * $d2 + $f2,
        ];
    }

    private function generatePdf(GiftCardInterface $giftCard, string $pageSize): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $publicDir = self::getContainer()->getParameter('sylius_core.public_dir');
        self::assertIsString($publicDir);

        $generator = new DompdfGiftCardPdfGenerator(
            $twig,
            '@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig',
            $pageSize,
            $publicDir,
        );

        return $generator->generate($giftCard);
    }

    private function renderPdfHtml(GiftCardInterface $giftCard, string $localeCode, ?string $backImagePath = null): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig', [
            'giftCard' => $giftCard,
            'localeCode' => $localeCode,
            'frontImagePath' => null,
            'backImagePath' => $backImagePath,
        ]);
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

    private function createGiftCard(string $code = 'PDFTEST0000000001'): GiftCardInterface
    {
        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode($code);
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
