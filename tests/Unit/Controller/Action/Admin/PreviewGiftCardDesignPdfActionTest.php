<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\PreviewGiftCardDesignPdfAction;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The preview is requested from the admin, so the channel cannot come from the shop channel context: that
 * resolves by request hostname and has nothing to fall back to on a multi-channel shop. The design itself
 * knows which channels it is sold in, so the preview is rendered for one of those
 */
final class PreviewGiftCardDesignPdfActionTest extends TestCase
{
    use ProphecyTrait;

    private const DESIGN_ID = 7;

    private const PDF = '%PDF-preview';

    private const PREVIEW_MESSAGE = 'Enjoy your gift card!';

    /** The gift card the factory hands out, so the test can see which channel it was created for */
    private GiftCard $giftCard;

    private bool $channelRepositoryConsulted = false;

    /** @test */
    public function it_renders_the_preview_for_the_first_channel_of_the_design(): void
    {
        $first = $this->channel('FIRST');
        $design = $this->design($first, $this->channel('SECOND'));

        $response = $this->action($design)(new Request(), self::DESIGN_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame(self::PDF, $response->getContent());

        self::assertSame($first, $this->giftCard->getChannel());
        self::assertSame($design, $this->giftCard->getDesign());
        self::assertSame(5000, $this->giftCard->getAmount());
        self::assertSame(5000, $this->giftCard->getInitialAmount());
        self::assertSame(self::PREVIEW_MESSAGE, $this->giftCard->getCustomMessage());
        self::assertFalse($this->channelRepositoryConsulted, 'A design with channels needs no fallback channel');
    }

    /** @test */
    public function it_renders_the_preview_for_the_requested_channel(): void
    {
        $second = $this->channel('SECOND');
        $design = $this->design($this->channel('FIRST'), $second);

        $this->action($design)(new Request(['channel' => 'SECOND']), self::DESIGN_ID);

        self::assertSame($second, $this->giftCard->getChannel());
    }

    /** @test */
    public function it_treats_an_empty_channel_parameter_as_absent(): void
    {
        $first = $this->channel('FIRST');
        $design = $this->design($first, $this->channel('SECOND'));

        $this->action($design)(new Request(['channel' => '']), self::DESIGN_ID);

        self::assertSame($first, $this->giftCard->getChannel());
    }

    /**
     * Answering with a different channel would show the wrong currency, which is what previewing through the
     * shop channel context did, so an unknown channel is refused rather than silently substituted
     *
     * @test
     */
    public function it_rejects_a_requested_channel_the_design_is_not_assigned_to(): void
    {
        $design = $this->design($this->channel('FIRST'));
        $action = $this->action($design, $this->channel('OTHER'));

        $this->expectException(NotFoundHttpException::class);

        $action(new Request(['channel' => 'OTHER']), self::DESIGN_ID);
    }

    /** @test */
    public function it_falls_back_to_the_first_channel_of_the_shop_when_the_design_has_no_channels(): void
    {
        $fallback = $this->channel('FALLBACK');

        $this->action($this->design(), $fallback)(new Request(), self::DESIGN_ID);

        self::assertSame($fallback, $this->giftCard->getChannel());
    }

    /** @test */
    public function it_throws_not_found_when_there_is_no_channel_at_all(): void
    {
        $action = $this->action($this->design(), null);

        $this->expectException(NotFoundHttpException::class);

        $action(new Request(), self::DESIGN_ID);
    }

    /** @test */
    public function it_throws_not_found_for_an_unknown_design(): void
    {
        $action = $this->action(null);

        $this->expectException(NotFoundHttpException::class);

        $action(new Request(), self::DESIGN_ID);
    }

    private function channel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }

    private function design(ChannelInterface ...$channels): GiftCardDesignInterface
    {
        $design = new GiftCardDesign();
        $design->setCode('classic');
        foreach ($channels as $channel) {
            $design->addChannel($channel);
        }

        return $design;
    }

    /**
     * @param GiftCardDesignInterface|null $design the design the repository finds, null for an unknown id
     * @param ChannelInterface|null $fallbackChannel the first channel of the shop, null when there is none
     */
    private function action(?GiftCardDesignInterface $design, ?ChannelInterface $fallbackChannel = null): PreviewGiftCardDesignPdfAction
    {
        $giftCard = new GiftCard();
        $this->giftCard = $giftCard;

        $designRepository = $this->prophesize(GiftCardDesignRepositoryInterface::class);
        $designRepository->find(self::DESIGN_ID)->willReturn($design);

        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardFactory
            ->createForChannel(Argument::type(ChannelInterface::class))
            // Prophecy rebinds a callback's $this to the double being configured whenever the closure has
            // one, which every non-static closure does, whether or not it reads $this. Capturing $giftCard
            // through `use` instead of reaching for $this->giftCard sidesteps that rebinding
            ->will(static function (array $args) use ($giftCard): GiftCardInterface {
                /** @var ChannelInterface $channel */
                $channel = $args[0];
                $giftCard->setChannel($channel);

                return $giftCard;
            })
        ;

        $pdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $pdfGenerator->generate($giftCard)->willReturn(self::PDF);

        $channelRepositoryConsulted = &$this->channelRepositoryConsulted;

        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository->findOneBy([])->will(static function () use ($fallbackChannel, &$channelRepositoryConsulted): ?ChannelInterface {
            $channelRepositoryConsulted = true;

            return $fallbackChannel;
        });

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('setono_sylius_gift_card.ui.preview_message')->willReturn(self::PREVIEW_MESSAGE);

        return new PreviewGiftCardDesignPdfAction(
            $designRepository->reveal(),
            $giftCardFactory->reveal(),
            $pdfGenerator->reveal(),
            $channelRepository->reveal(),
            $translator->reveal(),
        );
    }
}
