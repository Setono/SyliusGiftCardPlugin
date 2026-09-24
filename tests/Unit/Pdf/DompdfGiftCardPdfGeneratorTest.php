<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Pdf;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Pdf\DompdfGiftCardPdfGenerator;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

/**
 * What the generator works out before the template renders the card: the locale to write it in, the design images
 * as files dompdf may read, and the page the card is scaled onto. The rendering itself is covered by
 * GiftCardPdfGeneratorTest in the functional suite
 */
final class DompdfGiftCardPdfGeneratorTest extends TestCase
{
    use ProphecyTrait;

    private const TEMPLATE = '@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig';

    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/ssgc_pdf_' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->publicDir . '/media/image/ab/cd');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->publicDir);
    }

    /** @test */
    public function it_renders_the_configured_template_into_a_pdf(): void
    {
        $giftCard = $this->giftCard();

        [$pdf, $context] = $this->generate($giftCard);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertSame($giftCard, $context['giftCard']);
    }

    /**
     * The PDF is rendered outside the customer's request, when the order is paid or when an admin sends or
     * downloads it, so it is written in the locale the card was bought in
     *
     * @test
     */
    public function it_writes_a_bought_card_in_the_locale_of_its_order(): void
    {
        $giftCard = $this->giftCard();

        $order = new Order();
        $order->setLocaleCode('da_DK');
        $item = new OrderItem();
        $order->addItem($item);
        $giftCard->setOrderItemUnit(new OrderItemUnit($item));

        self::assertSame('da_DK', $this->generate($giftCard)[1]['localeCode']);
    }

    /** @test */
    public function it_writes_a_card_issued_in_the_admin_in_its_channels_default_locale(): void
    {
        self::assertSame('en_US', $this->generate($this->giftCard())[1]['localeCode']);
    }

    /**
     * Without a locale to go by, the template falls back to the one Sylius' money formatter uses
     *
     * @test
     */
    public function it_leaves_the_locale_to_the_template_when_there_is_none(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('PDFTEST000000001');
        $giftCard->setChannel(new Channel());

        self::assertNull($this->generate($giftCard)[1]['localeCode']);
    }

    /**
     * Dompdf is chrooted to the public directory and does not load remote resources, so the design images reach
     * it as absolute paths to the uploaded files under the media directory
     *
     * @test
     */
    public function it_hands_the_template_the_design_images_as_files_in_the_public_directory(): void
    {
        file_put_contents($this->publicDir . '/media/image/ab/cd/front.png', 'png');
        file_put_contents($this->publicDir . '/media/image/ab/cd/back.png', 'png');

        $giftCard = $this->giftCard();
        $giftCard->setDesign($this->design(front: 'ab/cd/front.png', back: '/ab/cd/back.png'));

        $context = $this->generate($giftCard)[1];

        self::assertSame($this->publicDir . '/media/image/ab/cd/front.png', $context['frontImagePath']);
        self::assertSame($this->publicDir . '/media/image/ab/cd/back.png', $context['backImagePath']);
    }

    /**
     * An image whose file is gone (a copied database, a cleared media directory) would be drawn as dompdf's
     * broken image placeholder, so the card falls back to the look it has without artwork
     *
     * @test
     */
    public function it_leaves_out_a_design_image_whose_file_is_missing(): void
    {
        file_put_contents($this->publicDir . '/media/image/ab/cd/front.png', 'png');

        $giftCard = $this->giftCard();
        $giftCard->setDesign($this->design(front: 'ab/cd/front.png', back: 'ab/cd/missing.png'));

        $context = $this->generate($giftCard)[1];

        self::assertNotNull($context['frontImagePath']);
        self::assertNull($context['backImagePath']);
    }

    /** @test */
    public function it_renders_a_card_without_a_design_without_artwork(): void
    {
        $context = $this->generate($this->giftCard())[1];

        self::assertNull($context['frontImagePath']);
        self::assertNull($context['backImagePath']);
    }

    /**
     * The card is drawn on a 560 pixel wide grid and scaled onto the page, which is always landscape
     *
     * @test
     *
     * @dataProvider pageSizes
     */
    public function it_scales_the_card_onto_the_configured_page_in_landscape(string $pageSize, float $widthInPoints, float $heightInPoints): void
    {
        $context = $this->generate($this->giftCard(), $pageSize)[1];

        $pageWidth = $widthInPoints * 96 / 72;
        $pageHeight = $heightInPoints * 96 / 72;
        $scale = $pageWidth / 560;

        self::assertEqualsWithDelta($pageWidth, $context['pageWidth'], 0.001);
        self::assertEqualsWithDelta($pageHeight, $context['pageHeight'], 0.001);
        self::assertEqualsWithDelta($scale, $context['scale'], 0.00001);
        self::assertSame(560.0, $context['cardWidth']);
        self::assertEqualsWithDelta($pageHeight / $scale, $context['cardHeight'], 0.001);
    }

    /**
     * @return iterable<string, array{string, float, float}>
     */
    public static function pageSizes(): iterable
    {
        yield 'A6, the size the card was drawn for' => ['A6', 419.53, 297.64];
        yield 'A4, in any case' => ['a4', 841.89, 595.28];
        yield 'letter, which is portrait in dompdf' => ['letter', 792.0, 612.0];
        // dompdf draws a size it does not know on letter, so the scale has to be worked out for letter as well
        yield 'a size dompdf does not know' => ['postcard', 792.0, 612.0];
    }

    /**
     * @return array{string, array<array-key, mixed>} the PDF, and the context the template was rendered with
     */
    private function generate(GiftCard $giftCard, string $pageSize = 'A6'): array
    {
        $context = [];

        $twig = $this->prophesize(Environment::class);
        $twig->render(self::TEMPLATE, Argument::type('array'))->will(static function (array $arguments) use (&$context): string {
            self::assertIsArray($arguments[1]);
            $context = $arguments[1];

            return '<html><body><p>Gift card</p></body></html>';
        })->shouldBeCalledOnce();

        $generator = new DompdfGiftCardPdfGenerator($twig->reveal(), self::TEMPLATE, $pageSize, $this->publicDir);

        return [$generator->generate($giftCard), $context];
    }

    private function giftCard(): GiftCard
    {
        $locale = new Locale();
        $locale->setCode('en_US');

        $channel = new Channel();
        $channel->setDefaultLocale($locale);

        $giftCard = new GiftCard();
        $giftCard->setCode('PDFTEST000000001');
        $giftCard->setChannel($channel);

        return $giftCard;
    }

    private function design(string $front, string $back): GiftCardDesign
    {
        $design = new GiftCardDesign();

        foreach ([GiftCardDesignImageInterface::TYPE_FRONT => $front, GiftCardDesignImageInterface::TYPE_BACK => $back] as $type => $path) {
            $image = new GiftCardDesignImage();
            $image->setType($type);
            $image->setPath($path);
            $design->addImage($image);
        }

        return $design;
    }
}
