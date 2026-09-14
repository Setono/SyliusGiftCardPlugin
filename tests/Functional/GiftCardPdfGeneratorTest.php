<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
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

    private function renderPdfHtml(GiftCardInterface $giftCard, string $localeCode): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig', [
            'giftCard' => $giftCard,
            'localeCode' => $localeCode,
            'frontImagePath' => null,
            'backImagePath' => null,
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
