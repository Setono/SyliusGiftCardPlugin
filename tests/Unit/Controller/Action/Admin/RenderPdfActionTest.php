<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Controller\Action\Admin;

use Gaufrette\Filesystem;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\GeneratePdfAction;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\RenderPdfAction;
use Setono\SyliusGiftCardPlugin\Factory\DummyGiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfPathGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RenderPdfActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_exception_if_configuration_is_not_found(): void
    {
        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardPdfPathGenerator = $this->prophesize(GiftCardPdfPathGeneratorInterface::class);
        $filesystem = $this->prophesize(Filesystem::class);

        $action = new RenderPdfAction(
            $giftCardConfigurationRepository->reveal(),
            $giftCardPdfPathGenerator->reveal(),
            $filesystem->reveal()
        );

        $this->expectException(NotFoundHttpException::class);
        $action(new Request(), 1);
    }

    /**
     * @test
     */
    public function it_generates_pdf(): void
    {
        $id = 8;
        $giftCardConfiguration = new GiftCardConfiguration();
        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardConfigurationRepository->find($id)->willReturn($giftCardConfiguration);
        $giftCardPdfPathGenerator = $this->prophesize(GiftCardPdfPathGeneratorInterface::class);
        $filesystem = $this->prophesize(Filesystem::class);

        $action = new RenderPdfAction(
            $giftCardConfigurationRepository->reveal(),
            $giftCardPdfPathGenerator->reveal(),
            $filesystem->reveal()
        );

        $giftCardPdfPathGenerator->generatePath($giftCardConfiguration)->shouldBeCalled();

        $response = $action(new Request(), $id);
        $headers = $response->headers;
        $this->assertEquals(
            $response->headers->makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'gift_card.pdf'),
            $headers->get('Content-Disposition')
        );
        $cacheControlHeaders = $headers->get('Cache-Control');
        $this->assertEquals('max-age=0, must-revalidate, public', $cacheControlHeaders);
    }
}
