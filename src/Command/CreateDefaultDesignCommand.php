<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Command;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;
use Webmozart\Assert\Assert;

/**
 * Creates the bundled 'Classic' gift card design and makes it available in every channel.
 *
 * This used to happen lazily while rendering the product page, i.e. during a GET request. Creating the design is
 * now an explicit, idempotent operation: an existing design with the default code is reused and only the channels
 * it is missing are added, so it is safe to run again after adding a channel to the shop.
 */
#[AsCommand(
    name: 'setono:gift-card:create-default-design',
    description: 'Creates the default "Classic" gift card design and adds it to every channel',
)]
final class CreateDefaultDesignCommand extends Command
{
    use ORMTrait;

    public const DEFAULT_DESIGN_CODE = 'classic';

    /**
     * @param FactoryInterface<GiftCardDesignInterface> $designFactory
     * @param FactoryInterface<GiftCardDesignImageInterface> $designImageFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly GiftCardDesignRepositoryInterface $designRepository,
        private readonly FactoryInterface $designFactory,
        private readonly FactoryInterface $designImageFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ImageUploaderInterface $imageUploader,
        ManagerRegistry $managerRegistry,
        private readonly string $defaultImagePath,
    ) {
        parent::__construct();

        $this->managerRegistry = $managerRegistry;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $design = $this->designRepository->findOneBy(['code' => self::DEFAULT_DESIGN_CODE]);
        Assert::nullOrIsInstanceOf($design, GiftCardDesignInterface::class);

        if (null === $design) {
            $design = $this->createDesign();

            $io->text(sprintf('Created the default gift card design "%s"', self::DEFAULT_DESIGN_CODE));
        } else {
            $io->text(sprintf('The default gift card design "%s" already exists', self::DEFAULT_DESIGN_CODE));
        }

        $addedChannels = [];

        foreach ($this->channelRepository->findAll() as $channel) {
            if ($design->hasChannel($channel)) {
                continue;
            }

            $design->addChannel($channel);
            $addedChannels[] = (string) $channel->getCode();
        }

        $manager = $this->getManager($design);
        $manager->persist($design);
        $manager->flush();

        if ([] === $addedChannels) {
            $io->text('The design is already available in every channel');
        } else {
            $io->text(sprintf('Added the design to the channel(s): %s', implode(', ', $addedChannels)));
        }

        return Command::SUCCESS;
    }

    private function createDesign(): GiftCardDesignInterface
    {
        $design = $this->designFactory->createNew();
        $design->setCode(self::DEFAULT_DESIGN_CODE);
        $design->setName('Classic');
        $design->setEnabled(true);
        $design->setPosition(0);

        $image = $this->designImageFactory->createNew();
        $image->setType(GiftCardDesignImageInterface::TYPE_FRONT);
        $image->setFile(new File($this->defaultImagePath));

        $design->addImage($image);

        $this->imageUploader->upload($image);

        return $design;
    }
}
