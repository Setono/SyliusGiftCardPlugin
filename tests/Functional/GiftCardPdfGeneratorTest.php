<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
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
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('@SetonoSyliusGiftCardPlugin/shop/gift_card/pdf.html.twig', [
            'giftCard' => $this->createGiftCard('SUMMER26'),
            'localeCode' => 'en_US',
            'frontImagePath' => null,
            'backImagePath' => null,
        ]);

        self::assertStringContainsString('SUMM-ER26', $html);
        self::assertStringNotContainsString('SUMM-ER26-', $html);
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
