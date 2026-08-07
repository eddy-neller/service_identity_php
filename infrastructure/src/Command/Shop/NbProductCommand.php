<?php

declare(strict_types=1);

namespace App\Infrastructure\Command\Shop;

use App\Infrastructure\Entity\Shop\Category;
use App\Infrastructure\Entity\Shop\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:shop:nb-product',
    description: 'Set up number of ShopProduct for ShopCategory related.',
    hidden: false,
)]
class NbProductCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nb ShopProduct For ShopCategory Command');

        $nbMajs = 0;

        $categories = $this->em->getRepository(Category::class)->findAll();

        foreach ($categories as $category) {
            /* @var Category $category */
            if ($output->isVerbose()) {
                $io->section('[CATEGORY] : ' . $category->getTitle());
            }

            $currentNbProduct = $category->getNbProduct();

            $nbProductFound = (int) $this->em->getRepository(Product::class)->countNbProductByCategory($category->getId()->toString());

            // test si une différence est détectée
            if ($nbProductFound !== $currentNbProduct) {
                if ($output->isVerbose()) {
                    $io->warning('DIFFERENCE DETECTED.');
                }

                $category->setNbProduct($nbProductFound);
                ++$nbMajs;

                if ($output->isVerbose()) {
                    $io->info('FROM "' . $currentNbProduct . '" TO "' . $nbProductFound . '".');
                }
            } else {
                if ($output->isVerbose()) {
                    $io->text('CLEAN.');
                }

                if ($output->isDebug()) {
                    $io->text([
                        '',
                        'NB Items registered : ' . $currentNbProduct,
                        'NB Items found : ' . $nbProductFound,
                    ]);
                }
            }
        }

        if (!$output->isDebug()) {
            $this->em->flush();
        }

        if (0 === $nbMajs) {
            $io->success('Aucune modification effectuée.');
        } else {
            $io->warning($nbMajs . ' modification(s) effectuée(s).');
        }

        return Command::SUCCESS;
    }
}
