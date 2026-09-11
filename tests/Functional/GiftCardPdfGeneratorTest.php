<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;

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
}
