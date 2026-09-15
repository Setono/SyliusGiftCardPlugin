<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardInformationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The form is built while the product page renders, i.e. on a GET request, so building it must only read. It used to
 * create a default design on the fly for a channel without designs, which wrote to the database during rendering and
 * blew up with an uncaught unique constraint violation on the second channel without designs.
 */
final class GiftCardInformationTypeTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_builds_for_every_channel_without_designs_and_creates_none(): void
    {
        $first = $this->getChannel();
        $first->setHostname('first.example.test');
        $this->manager->flush();

        $second = $this->createChannel('SECOND_CHANNEL', 'second.example.test');

        // both channels are rendered before anything is asserted: the old lazy creation only blew up on the
        // second one, where the design it had just created for the first one violated the unique code
        $choices = [];

        foreach ([$first, $second] as $channel) {
            $this->pushRequestFor($channel);

            $form = $this->createForm();
            $form->createView();

            $choices[] = $form->get('design')->getConfig()->getOption('choices');
        }

        self::assertSame([[], []], $choices);
        self::assertSame([], $this->designRepository()->findAll());
        self::assertSame([], $this->manager->getUnitOfWork()->getScheduledEntityInsertions());
    }

    /** @test */
    public function it_does_not_require_a_design_when_the_channel_has_none(): void
    {
        $this->pushRequestFor($this->channelWithHostname());

        $form = $this->createForm();
        $form->submit(['amount' => '25']);

        self::assertCount(0, $form->get('design')->getErrors());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $data = $form->getData();
        self::assertInstanceOf(GiftCardInformationInterface::class, $data);
        self::assertNull($data->getDesign());
    }

    /** @test */
    public function it_requires_one_of_the_channel_designs_when_there_are_any(): void
    {
        $channel = $this->channelWithHostname();
        $design = $this->createDesign('classic', $channel);
        $this->pushRequestFor($channel);

        $form = $this->createForm();
        self::assertSame([$design], $form->get('design')->getConfig()->getOption('choices'));

        $form->submit(['amount' => '25', 'design' => '']);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('design')->getErrors());
        self::assertCount(1, $this->designRepository()->findAll());
    }

    /**
     * @return FormInterface<GiftCardInformationInterface>
     */
    private function createForm(): FormInterface
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        /** @var FormInterface<GiftCardInformationInterface> $form */
        $form = $formFactory->create(GiftCardInformationType::class, new GiftCardInformation(0), [
            // the type is embedded in the add to cart form in production, so the root form owns the token
            'csrf_protection' => false,
        ]);

        return $form;
    }

    private function channelWithHostname(): ChannelInterface
    {
        $channel = $this->getChannel();
        $channel->setHostname('first.example.test');

        $this->manager->flush();

        return $channel;
    }

    /**
     * The type resolves the channel from the current request the way the product page does, so a request for the
     * channel hostname is made the current one
     */
    private function pushRequestFor(ChannelInterface $channel): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        while (null !== $requestStack->pop()) {
        }

        $requestStack->push(Request::create(sprintf('http://%s/', (string) $channel->getHostname())));
    }

    private function createDesign(string $code, ChannelInterface $channel): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setName('Classic');
        $design->setEnabled(true);
        $design->addChannel($channel);

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }

    private function designRepository(): GiftCardDesignRepositoryInterface
    {
        /** @var GiftCardDesignRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card_design');

        return $repository;
    }
}
