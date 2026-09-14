<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\DompdfGiftCardPdfGenerator;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

final class GiftCardPdfGeneratorTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_generates_a_two_page_pdf(): void
    {
        /** @var GiftCardPdfGeneratorInterface $generator */
        $generator = self::getContainer()->get(GiftCardPdfGeneratorInterface::class);

        $pdf = $generator->generate($this->createGiftCard());

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
     * The back used to print a hardcoded array of bar widths: the same drawing on every card, encoding nothing,
     * next to copy telling the customer to have it scanned in a store
     *
     * @test
     */
    public function it_pictures_the_code_as_a_real_barcode(): void
    {
        $html = $this->renderPdfHtml($this->createGiftCard('PDFTEST0000000001'), 'en_US');
        $other = $this->renderPdfHtml($this->createGiftCard('PDFTEST0000000002'), 'en_US');

        self::assertSame(1, preg_match('#<img src="data:image/svg\+xml;base64,([^"]+)"#', $html, $matches));
        self::assertSame(1, preg_match('#<img src="data:image/svg\+xml;base64,([^"]+)"#', $other, $otherMatches));

        $barcode = base64_decode($matches[1], true);
        self::assertIsString($barcode);
        self::assertStringStartsWith('<svg', $barcode);
        self::assertStringContainsString('<rect', $barcode);

        // a barcode that pictures the code differs from card to card; the hardcoded one never did
        self::assertNotSame($matches[1], $otherMatches[1]);
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
        foreach (['SUMM-ER26', 'HOW TO REDEEM', 'REDEMPTION CODE', 'data:image/svg+xml;base64,'] as $expected) {
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
     * The horizontal offset in points of the leftmost piece of text in the PDF. Text positions are the only part
     * of the layout that survives into the content stream in a form that is cheap to read back
     */
    private function leftmostTextOffset(string $pdf): float
    {
        $offsets = [];

        preg_match_all('#stream(.*?)endstream#s', $pdf, $streams);
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress(ltrim($stream, "\r\n"));
            if (!is_string($content)) {
                continue;
            }

            if (preg_match_all('#BT ([0-9.]+) [0-9.]+ Td#', $content, $matches) > 0) {
                foreach ($matches[1] as $offset) {
                    $offsets[] = (float) $offset;
                }
            }
        }

        self::assertNotEmpty($offsets, 'The PDF contains no text to measure the layout by');

        return min($offsets);
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
}
