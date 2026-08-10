<?php

declare(strict_types=1);

namespace App\Infrastructure\Command\User;

use App\Infrastructure\Entity\User\User;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use App\Infrastructure\Service\Media\CustomImageProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de maintenance pour générer des images d'avatar aléatoires.
 */
#[AsCommand(
    name: 'app:user:generate-image',
    description: 'Génère les images aléatoires pour les utilisateurs sans image',
)]
class GenerateUserImagesCommand extends Command
{
    private const string AVATAR_DIR = 'public/uploads/images/user/avatar';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly CustomImageProvider $customImageProvider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();
        $totalUsers = count($users);
        $count = 0;

        if (0 === $totalUsers) {
            $io->info('Aucun utilisateur à traiter.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Traitement de %d utilisateur(s)...', $totalUsers));
        $io->progressStart($totalUsers);

        /** @var User $user */
        foreach ($users as $user) {
            if ($user->getAvatarName()) {
                $io->progressAdvance();
                continue;
            }

            $avatar = $this->customImageProvider->customImage(self::AVATAR_DIR, 96, 96);

            if (!$avatar) {
                $io->progressAdvance();
                $io->warning(sprintf("Échec de génération des images pour l'utilisateur \"%s\".", $user->getFullname()));
                continue;
            }

            $user->setAvatarName($avatar);
            $user->setUpdatedAt(new DateTimeImmutable());

            ++$count;
            $io->progressAdvance();
        }

        $io->progressFinish();

        if ($count > 0) {
            $this->em->flush();
        }

        $io->success($count . ' utilisateur(s) mis à jour.');

        return Command::SUCCESS;
    }
}
